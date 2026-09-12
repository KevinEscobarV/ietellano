<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Cycle;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Porcentaje de inasistencias sin justificar a partir del cual se pierde la
     * materia. Se configura en config/institution.php.
     */
    public static function maxAbsenceRate(): float
    {
        return (float) config('institution.max_absence_rate', 0.2);
    }

    /**
     * Crea las sesiones que falten del calendario del ciclo, sin tocar las que
     * ya existen: un festivo que alguien borró a mano no vuelve a aparecer
     * mientras no se corra de nuevo.
     *
     * @return int cuántas se crearon
     */
    public function generateSessions(Cycle $cycle): int
    {
        if ($cycle->starts_on === null || $cycle->ends_on === null || $cycle->class_weekday === null) {
            return 0;
        }

        $existing = $cycle->sessions()->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->flip();

        $created = 0;

        foreach (CarbonPeriod::create($cycle->starts_on, $cycle->ends_on) as $day) {
            if ($day->dayOfWeekIso !== $cycle->class_weekday || $existing->has($day->toDateString())) {
                continue;
            }

            ClassSession::create(['cycle_id' => $cycle->id, 'date' => $day->toDateString()]);
            $created++;
        }

        return $created;
    }

    /**
     * Cuántas clases se dictaron y cuántas se perdieron, por curso.
     *
     * Una clase cuenta como dictada cuando el docente pasó asistencia: si nunca
     * la pasó, el curso no tiene denominador y no se le reprocha nada a nadie.
     *
     * @param  Collection<int, Course>|list<int>  $courses
     * @return array<int, array{held: int, absences: int, excused: int, rate: ?float, lost: bool}>
     */
    public function byCourse(int $studentId, Collection|array $courses): array
    {
        $ids = $courses instanceof Collection ? $courses->pluck('id')->all() : $courses;

        if ($ids === []) {
            return [];
        }

        $held = Attendance::query()
            ->whereIn('course_id', $ids)
            ->selectRaw('course_id, count(distinct class_session_id) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $rows = Attendance::query()
            ->where('student_id', $studentId)
            ->whereIn('course_id', $ids)
            ->selectRaw('course_id, status, count(*) as total')
            ->groupBy('course_id', 'status')
            ->get();

        $summary = [];

        foreach ($ids as $id) {
            $absences = (int) ($rows->first(fn ($r) => (int) $r->course_id === $id && $r->status === AttendanceStatus::Absent)?->total ?? 0);
            $excused = (int) ($rows->first(fn ($r) => (int) $r->course_id === $id && $r->status === AttendanceStatus::Excused)?->total ?? 0);

            $summary[$id] = $this->line((int) ($held[$id] ?? 0), $absences, $excused);
        }

        return $summary;
    }

    /**
     * Lo mismo pero agrupado por materia, que es como lo lee el boletín: una
     * materia puede tener un curso por periodo y hasta dos semestres.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, array{held: int, absences: int, excused: int, rate: ?float, lost: bool}>
     */
    public function bySubject(int $studentId, Collection $courses): array
    {
        $byCourse = $this->byCourse($studentId, $courses);
        $totals = [];

        foreach ($courses as $course) {
            $line = $byCourse[$course->id] ?? null;

            if ($line === null) {
                continue;
            }

            $totals[$course->subject_id]['held'] = ($totals[$course->subject_id]['held'] ?? 0) + $line['held'];
            $totals[$course->subject_id]['absences'] = ($totals[$course->subject_id]['absences'] ?? 0) + $line['absences'];
            $totals[$course->subject_id]['excused'] = ($totals[$course->subject_id]['excused'] ?? 0) + $line['excused'];
        }

        return array_map(
            fn (array $t) => $this->line($t['held'], $t['absences'], $t['excused']),
            $totals,
        );
    }

    /**
     * Fallas por materia de varios estudiantes a la vez, en dos consultas. Es
     * lo que necesita la consulta del panel: llamar a bySubject() por cada
     * estudiante serían dos consultas por fila.
     *
     * @param  list<int>  $studentIds
     * @param  Collection<int, Course>  $courses
     * @return array<int, array<int, array{held: int, absences: int, excused: int, rate: ?float, lost: bool}>>
     */
    public function matrix(array $studentIds, Collection $courses): array
    {
        if ($studentIds === [] || $courses->isEmpty()) {
            return [];
        }

        $subjectOf = $courses->pluck('subject_id', 'id');

        $heldByCourse = Attendance::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->selectRaw('course_id, count(distinct class_session_id) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $rows = Attendance::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('course_id', $courses->pluck('id'))
            ->selectRaw('student_id, course_id, status, count(*) as total')
            ->groupBy('student_id', 'course_id', 'status')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $subjectId = (int) $subjectOf[$row->course_id];
            $studentId = (int) $row->student_id;

            // Las clases dictadas se cuentan una sola vez por curso y estudiante.
            $totals[$studentId][$subjectId]['courses'][$row->course_id] = (int) ($heldByCourse[$row->course_id] ?? 0);

            $key = $row->status === AttendanceStatus::Absent ? 'absences' : ($row->status === AttendanceStatus::Excused ? 'excused' : null);

            if ($key !== null) {
                $totals[$studentId][$subjectId][$key] = ($totals[$studentId][$subjectId][$key] ?? 0) + (int) $row->total;
            }
        }

        return array_map(
            fn (array $subjects) => array_map(
                fn (array $t) => $this->line(
                    (int) array_sum($t['courses'] ?? []),
                    $t['absences'] ?? 0,
                    $t['excused'] ?? 0,
                ),
                $subjects,
            ),
            $totals,
        );
    }

    /**
     * Lo que lleva acumulado cada estudiante en un curso. Es lo que el docente
     * necesita ver al lado del nombre mientras pasa lista.
     *
     * @return array<int, array{held: int, absences: int, excused: int, rate: ?float, lost: bool}>
     */
    public function studentTotals(Course $course): array
    {
        $held = (int) Attendance::where('course_id', $course->id)
            ->distinct()
            ->count('class_session_id');

        return Attendance::query()
            ->where('course_id', $course->id)
            ->selectRaw('student_id, status, count(*) as total')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $this->line(
                $held,
                (int) ($rows->firstWhere('status', AttendanceStatus::Absent)?->total ?? 0),
                (int) ($rows->firstWhere('status', AttendanceStatus::Excused)?->total ?? 0),
            ))
            ->all();
    }

    /**
     * Avance de un curso: cuántas sesiones del ciclo ya tienen asistencia
     * pasada y cuántas faltan.
     *
     * @return array{held: int, total: int, pending: int}
     */
    public function courseProgress(Course $course): array
    {
        return $this->progressFor(collect([$course]))[$course->id];
    }

    /**
     * Lo mismo para varios cursos a la vez, en dos consultas, para las listas
     * donde llamarlo curso por curso sería una consulta por tarjeta.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, array{held: int, total: int, pending: int}>
     */
    public function progressFor(Collection $courses): array
    {
        if ($courses->isEmpty()) {
            return [];
        }

        $total = ClassSession::query()
            ->whereIn('cycle_id', $courses->pluck('cycle_id')->unique())
            ->selectRaw('cycle_id, count(*) as total')
            ->groupBy('cycle_id')
            ->pluck('total', 'cycle_id');

        $held = Attendance::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->selectRaw('course_id, count(distinct class_session_id) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        return $courses
            ->mapWithKeys(function (Course $course) use ($total, $held) {
                $sessions = (int) ($total[$course->cycle_id] ?? 0);
                $taken = (int) ($held[$course->id] ?? 0);

                return [$course->id => [
                    'held' => $taken,
                    'total' => $sessions,
                    'pending' => max($sessions - $taken, 0),
                ]];
            })
            ->all();
    }

    /**
     * @return array{held: int, absences: int, excused: int, rate: ?float, lost: bool}
     */
    private function line(int $held, int $absences, int $excused): array
    {
        $rate = $held > 0 ? $absences / $held : null;

        return [
            'held' => $held,
            'absences' => $absences,
            'excused' => $excused,
            'rate' => $rate,
            'lost' => $rate !== null && $rate > self::maxAbsenceRate(),
        ];
    }
}
