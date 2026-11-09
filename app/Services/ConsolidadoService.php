<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * El consolidado de un ciclo: las notas de todos sus estudiantes en una sola
 * tabla, con un resumen para leer de un vistazo cómo le fue al ciclo. Cada
 * fila sale del boletín del estudiante, así que las cuentas son las mismas:
 * la definitiva es el promedio de los dos módulos y las materias de un área
 * (Física y Química en los ciclos 5 y 6) van juntas.
 *
 * Los ciclos 3 y 4 duran un año: 3A es el primer semestre y 3B el segundo
 * (cycles.previous_cycle_id). Su consolidado se lee por año: la nota de cada
 * materia es el promedio de la definitiva de los dos semestres, la misma
 * cuenta del certificado.
 */
class ConsolidadoService
{
    /**
     * Qué nota se lee: la definitiva o la de un solo módulo.
     */
    public const VIEWS = ['definitiva', 'm1', 'm2'];

    public const SORTS = ['nombre', 'puesto'];

    public function __construct(
        private BoletinService $boletines,
        private TermService $term,
        private AttendanceService $attendance,
    ) {}

    /**
     * Con $wholeYear y un ciclo que viene de un primer semestre, se lee el año
     * completo: entran todos los estudiantes del segundo semestre, los que no
     * cursaron el primero quedan marcados y sin puesto, y el resumen cuenta
     * cuántos del primero no siguieron. Un ciclo sin semestre anterior se lee
     * siempre por semestre.
     *
     * @return array<string, mixed>
     */
    public function forCycle(Cycle $cycle, string $view = 'definitiva', bool $wholeYear = false, ?int $groupId = null, string $sort = 'nombre'): array
    {
        $previous = $cycle->previousCycle;
        $year = $wholeYear && $previous !== null;
        $view = ! $year && in_array($view, self::VIEWS, true) ? $view : 'definitiva';
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'nombre';
        $group = $groupId !== null ? $cycle->groups()->find($groupId) : null;

        $tookPrevious = $previous
            ? Enrollment::where('cycle_id', $previous->id)->pluck('student_id')->flip()
            : collect();

        $columns = [];
        $rows = [];

        foreach ($this->students($cycle, $group) as $student) {
            $boletin = $this->boletines->generate($student, $cycle, $year);
            $cells = [];

            foreach ($boletin['lines'] as $line) {
                $columns[$line['name']] ??= [
                    'name' => $line['name'],
                    'components' => array_column($line['components'], 'name'),
                ];

                $cells[$line['name']] = $this->cell($line, $view, $year);
            }

            $average = BoletinService::average(array_column($cells, 'score'));

            $rows[] = [
                'id' => $student->id,
                'name' => trim("{$student->last_name} {$student->first_name}"),
                'document' => $student->document,
                'group' => $boletin['group'],
                'took_previous' => $tookPrevious->has($student->id),
                'cells' => $cells,
                'average' => $average,
                'performance' => BoletinService::performance($average),
                'failed' => count(array_filter($cells, fn (array $cell) => $cell['failed'])),
                'pending' => count(array_filter($cells, fn (array $cell) => ! $cell['complete'])),
                'rank' => null,
            ];
        }

        // Mismo orden de materias que el boletín.
        $columns = array_values($columns);
        usort($columns, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        // En el año, el puesto y las cuentas son de quienes cursaron los dos
        // semestres: con uno solo, la nota del año está a medias.
        $counts = fn (array $row) => ! $year || $row['took_previous'];

        $rows = $this->rank($rows, $counts);

        if ($sort === 'puesto') {
            usort($rows, fn (array $a, array $b) => [$a['rank'] === null, $a['rank'], $a['name']] <=> [$b['rank'] === null, $b['rank'], $b['name']]);
        }

        $counted = array_values(array_filter($rows, $counts));
        $stats = $this->columnStats($columns, $counted);
        $dropped = $year && $group === null ? $this->dropped($previous, $cycle) : [];

        return [
            'cycle' => $cycle,
            'group' => $group,
            'view' => $view,
            'year' => $year,
            'yearParts' => $previous ? ['previous' => $previous->level, 'current' => $cycle->level] : null,
            'continuesIn' => $cycle->nextCycle,
            'sort' => $sort,
            'title' => $this->title($view, $year),
            'scope' => $this->scope($cycle, $group, $year),
            'status' => $this->status($cycle, $view, $year ? $previous : null),
            'detailLabels' => $year ? [$previous->level, $cycle->level, __('Año')] : [__('M1'), __('M2'), __('Def.')],
            'showGroup' => $group === null && count(array_unique(array_filter(array_column($rows, 'group')))) > 1,
            'columns' => $columns,
            'rows' => $rows,
            'stats' => $stats,
            'summary' => [
                ...$this->summary($stats, $counted),
                'students' => count($rows),
                'without_previous' => $year ? count($rows) - count($counted) : 0,
                'dropped' => count($dropped),
            ],
            'dropped' => $dropped,
        ];
    }

    /**
     * Un consolidado por cada ciclo del semestre que tenga estudiantes. Con la
     * definitiva, los ciclos de un año salen con el año completo; por módulo
     * no, porque el módulo es de un solo semestre.
     *
     * @return list<array<string, mixed>>
     */
    public function forTerm(int $year, int $semester, string $view = 'definitiva'): array
    {
        return Cycle::query()
            ->where('year', $year)
            ->where('semester', $semester)
            ->whereHas('enrollments')
            ->ordered()
            ->get()
            ->map(fn (Cycle $cycle) => $this->forCycle($cycle, $view, $view === 'definitiva'))
            ->all();
    }

    /**
     * Lo de un docente en un ciclo: una sección por cada materia y grupo que
     * dicta, con la nota de cada módulo, la definitiva y las fallas de cada
     * estudiante. En el segundo semestre de un ciclo de un año suma la
     * definitiva del primero y la del año. Las materias de los demás docentes
     * no aparecen.
     *
     * @return array{teacher: Teacher, cycle: Cycle, scope: string, status: array{definitive: bool, label: string, detail: string}, yearParts: ?array{previous: string, current: string}, sections: list<array<string, mixed>>}
     */
    public function forTeacher(Teacher $teacher, Cycle $cycle): array
    {
        $previous = $cycle->previousCycle;

        $own = Course::query()
            ->where('cycle_id', $cycle->id)
            ->where('teacher_id', $teacher->id)
            ->get(['subject_id', 'group_id']);

        // Los dos módulos de cada materia y grupo suyos, para sacar la
        // definitiva aunque uno de ellos lo tenga otro docente.
        $courses = Course::query()
            ->where('cycle_id', $cycle->id)
            ->whereIn('subject_id', $own->pluck('subject_id')->unique())
            ->with('subject', 'group')
            ->get()
            ->filter(fn (Course $course) => $own->contains(
                fn (Course $mine) => $mine->subject_id === $course->subject_id && $mine->group_id === $course->group_id,
            ));

        $sections = $courses
            ->groupBy(fn (Course $course) => $course->subject_id.'-'.($course->group_id ?? 0))
            ->map(fn (Collection $set) => $this->teacherSection($set, $previous))
            ->sortBy([['subject', 'asc'], ['group', 'asc']])
            ->values()
            ->all();

        return [
            'teacher' => $teacher,
            'cycle' => $cycle,
            'scope' => $cycle->label,
            // Con el año, el sello también espera al primer semestre.
            'status' => $this->status($cycle, 'definitiva', $previous),
            'yearParts' => $previous ? ['previous' => $previous->level, 'current' => $cycle->level] : null,
            'sections' => $sections,
        ];
    }

    /**
     * Definitivo cuando están cerrados los módulos que se leen; si no,
     * provisional y por qué. Con $previous (el año completo) también tiene que
     * estar cerrado el primer semestre.
     *
     * @return array{definitive: bool, label: string, detail: string}
     */
    public function status(Cycle $cycle, string $view = 'definitiva', ?Cycle $previous = null): array
    {
        $periods = match ($view) {
            'm1' => [1],
            'm2' => [2],
            default => Cycle::MODULES,
        };

        $modules = collect($periods)->mapWithKeys(fn (int $period) => [$period => $this->term->moduleStatus($cycle, $period)]);
        $open = $modules->reject(fn (array $module) => $module['state'] === 'closed');
        $of = $previous ? ' '.__('de :cycle', ['cycle' => $cycle->name]) : '';

        if ($open->count() > 1) {
            return [
                'definitive' => false,
                'label' => __('Provisional'),
                'detail' => __('Los módulos :list:of siguen abiertos', ['list' => $open->keys()->implode(' y '), 'of' => $of]),
            ];
        }

        if ($open->count() === 1) {
            $period = $open->keys()->first();
            $module = $open->first();

            return [
                'definitive' => false,
                'label' => __('Provisional'),
                'detail' => $module['state'] === 'scheduled'
                    ? __('El Módulo :n:of sigue abierto hasta el :date', ['n' => $period, 'of' => $of, 'date' => $this->date($module['at'])])
                    : __('El Módulo :n:of sigue abierto', ['n' => $period, 'of' => $of]),
            ];
        }

        if ($previous !== null && ! $this->term->isClosed($previous)) {
            return [
                'definitive' => false,
                'label' => __('Provisional'),
                'detail' => __(':cycle (:term) todavía tiene módulos abiertos', ['cycle' => $previous->name, 'term' => "{$previous->year}-{$previous->semester}"]),
            ];
        }

        return [
            'definitive' => true,
            'label' => __('Definitivo'),
            'detail' => __('Cerrado el :date', ['date' => $this->date($modules->pluck('at')->max())]),
        ];
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(Cycle $cycle, ?Group $group): Collection
    {
        return Student::query()
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('cycle_id', $cycle->id)
                ->when($group, fn (Builder $q) => $q->where('group_id', $group->id)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Quienes cursaron el primer semestre y no aparecen en el segundo, con su
     * promedio y cuántas materias perdieron allá.
     *
     * @return list<array{id: int, name: string, document: ?string, group: ?string, average: ?float, failed: int}>
     */
    private function dropped(Cycle $previous, Cycle $cycle): array
    {
        return Student::query()
            ->whereRelation('enrollments', 'cycle_id', $previous->id)
            ->whereDoesntHave('enrollments', fn (Builder $query) => $query->where('cycle_id', $cycle->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Student $student) use ($previous) {
                $boletin = $this->boletines->generate($student, $previous);

                return [
                    'id' => $student->id,
                    'name' => trim("{$student->last_name} {$student->first_name}"),
                    'document' => $student->document,
                    'group' => $boletin['group'],
                    'average' => $boletin['overall'],
                    'failed' => count(array_filter(
                        $boletin['lines'],
                        fn (array $line) => $line['final'] !== null && $line['final'] < BoletinService::PASSING_SCORE,
                    )),
                ];
            })
            ->all();
    }

    /**
     * La nota de una línea del boletín según lo que se lee. Está completa
     * cuando tiene las notas de los dos módulos (o de los dos semestres).
     *
     * @param  array<string, mixed>  $line
     * @return array{score: ?float, first: ?float, second: ?float, final: ?float, complete: bool, failed: bool}
     */
    private function cell(array $line, string $view, bool $year): array
    {
        $first = $year ? $line['sem1'] : $line['p1'];
        $second = $year ? $line['sem2'] : $line['p2'];

        [$score, $complete] = match ($view) {
            'm1' => [$first, $first !== null],
            'm2' => [$second, $second !== null],
            default => [$line['final'], $first !== null && $second !== null],
        };

        return [
            'score' => $score,
            'first' => $first,
            'second' => $second,
            'final' => $line['final'],
            'complete' => $complete,
            'failed' => $score !== null && $score < BoletinService::PASSING_SCORE,
        ];
    }

    /**
     * El puesto por promedio, de mayor a menor. Dos promedios iguales comparten
     * puesto y el siguiente lo salta (1, 2, 2, 4). Sin notas, o fuera de las
     * cuentas, no hay puesto.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): bool  $counts
     * @return list<array<string, mixed>>
     */
    private function rank(array $rows, callable $counts): array
    {
        $ranked = array_filter($rows, fn (array $row) => $row['average'] !== null && $counts($row));
        uasort($ranked, fn (array $a, array $b) => $b['average'] <=> $a['average']);

        $position = 0;
        $rank = 0;
        $previous = null;

        foreach ($ranked as $index => $row) {
            $position++;

            if ($row['average'] !== $previous) {
                $rank = $position;
                $previous = $row['average'];
            }

            $rows[$index]['rank'] = $rank;
        }

        return $rows;
    }

    /**
     * Promedio y cuántos aprueban, pierden o tienen la nota incompleta en cada
     * materia. Solo cuentan los estudiantes a los que les toca la materia.
     *
     * @param  list<array{name: string, components: list<string>}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{students: int, graded: int, average: ?float, passed: int, failed: int, pending: int}>
     */
    private function columnStats(array $columns, array $rows): array
    {
        $stats = [];

        foreach ($columns as $column) {
            $cells = array_values(array_filter(array_map(fn (array $row) => $row['cells'][$column['name']] ?? null, $rows)));
            $scores = array_values(array_filter(array_column($cells, 'score'), fn (?float $score) => $score !== null));
            $failed = count(array_filter($cells, fn (array $cell) => $cell['failed']));

            $stats[$column['name']] = [
                'students' => count($cells),
                'graded' => count($scores),
                'average' => BoletinService::average($scores),
                'passed' => count($scores) - $failed,
                'failed' => $failed,
                'pending' => count(array_filter($cells, fn (array $cell) => ! $cell['complete'])),
            ];
        }

        return $stats;
    }

    /**
     * La lectura rápida: promedio del ciclo, cuántos aprueban todo y cuántos
     * pierden 1, 2 o 3 materias o más, y dónde se pierde más.
     *
     * @param  array<string, array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summary(array $stats, array $rows): array
    {
        $graded = array_values(array_filter($rows, fn (array $row) => $row['average'] !== null));
        $average = BoletinService::average(array_column($graded, 'average'));

        $failing = [1 => 0, 2 => 0, 3 => 0];
        $levels = ['Superior' => 0, 'Alto' => 0, 'Básico' => 0, 'Bajo' => 0];

        foreach ($graded as $row) {
            if ($row['failed'] > 0) {
                $failing[min($row['failed'], 3)]++;
            }

            $levels[$row['performance']]++;
        }

        $hardest = collect($stats)
            ->map(fn (array $stat, string $name) => [...$stat, 'name' => $name])
            ->where('failed', '>', 0)
            ->sortByDesc('failed')
            ->take(3)
            ->values()
            ->all();

        return [
            'graded' => count($graded),
            'without_grades' => count($rows) - count($graded),
            'average' => $average,
            'performance' => BoletinService::performance($average),
            'all_passed' => count(array_filter($graded, fn (array $row) => $row['failed'] === 0)),
            'failing' => $failing,
            'failing_total' => array_sum($failing),
            'levels' => $levels,
            'hardest' => $hardest,
            'pending_students' => count(array_filter($rows, fn (array $row) => $row['pending'] > 0)),
            'pending_cells' => array_sum(array_column($rows, 'pending')),
        ];
    }

    /**
     * @param  Collection<int, Course>  $courses  los módulos de una materia en un grupo
     * @return array<string, mixed>
     */
    private function teacherSection(Collection $courses, ?Cycle $previous): array
    {
        $first = $courses->first();
        $byPeriod = $courses->keyBy('period');
        $students = $first->rosterQuery()->get();

        $grades = Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('score', 'course_id'));

        $attendance = $this->attendance->matrix($students->pluck('id')->all(), $courses);
        $earlier = $previous ? $this->previousFinals($previous, $first->subject_id, $students->pluck('id')) : [];

        $rows = $students->map(function (Student $student) use ($byPeriod, $grades, $attendance, $first, $previous, $earlier) {
            $scores = $grades->get($student->id, collect());
            $m1 = $this->score(isset($byPeriod[1]) ? $scores->get($byPeriod[1]->id) : null);
            $m2 = $this->score(isset($byPeriod[2]) ? $scores->get($byPeriod[2]->id) : null);
            $final = BoletinService::average([$m1, $m2]);
            $absences = $attendance[$student->id][$first->subject_id] ?? null;

            $tookPrevious = array_key_exists($student->id, $earlier);
            $before = $earlier[$student->id] ?? null;
            $year = $previous && $tookPrevious ? BoletinService::average([$before, $final]) : null;

            return [
                'id' => $student->id,
                'name' => trim("{$student->last_name} {$student->first_name}"),
                'document' => $student->document,
                'm1' => $m1,
                'm2' => $m2,
                'final' => $final,
                'complete' => $m1 !== null && $m2 !== null,
                'failed' => $final !== null && $final < BoletinService::PASSING_SCORE,
                'took_previous' => $tookPrevious,
                'previous' => $before,
                'year' => $year,
                'year_complete' => $tookPrevious && $before !== null && $m1 !== null && $m2 !== null,
                'year_failed' => $year !== null && $year < BoletinService::PASSING_SCORE,
                // En un ciclo de un año, el desempeño que cuenta es el del año.
                'performance' => BoletinService::performance($previous ? $year : $final),
                'absences' => $absences['absences'] ?? 0,
                'lost_by_absence' => $absences['lost'] ?? false,
            ];
        })->values()->all();

        $finals = array_values(array_filter(array_column($rows, 'final'), fn (?float $score) => $score !== null));
        $failed = count(array_filter($rows, fn (array $row) => $row['failed']));
        $years = array_values(array_filter(array_column($rows, 'year'), fn (?float $score) => $score !== null));
        $yearFailed = count(array_filter($rows, fn (array $row) => $row['year_failed']));

        return [
            'subject' => $first->subject->name,
            'group' => $first->group?->name,
            'periods' => $byPeriod->keys()->sort()->values()->all(),
            'year' => $previous !== null,
            'rows' => $rows,
            'stats' => [
                'students' => count($rows),
                'm1' => BoletinService::average(array_column($rows, 'm1')),
                'm2' => BoletinService::average(array_column($rows, 'm2')),
                'final' => BoletinService::average($finals),
                'passed' => count($finals) - $failed,
                'failed' => $failed,
                'pending' => count(array_filter($rows, fn (array $row) => ! $row['complete'])),
                'previous' => BoletinService::average(array_column($rows, 'previous')),
                'year' => BoletinService::average($years),
                'year_passed' => count($years) - $yearFailed,
                'year_failed' => $yearFailed,
                'without_previous' => $previous ? count(array_filter($rows, fn (array $row) => ! $row['took_previous'])) : 0,
            ],
        ];
    }

    /**
     * La definitiva de una materia en el primer semestre, por estudiante. Solo
     * trae a quienes estuvieron matriculados allá (la nota puede ser nula).
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, ?float>
     */
    private function previousFinals(Cycle $previous, int $subjectId, Collection $studentIds): array
    {
        $groups = Enrollment::query()
            ->where('cycle_id', $previous->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('group_id', 'student_id');

        $courses = Course::query()
            ->where('cycle_id', $previous->id)
            ->where('subject_id', $subjectId)
            ->get(['id', 'group_id']);

        $grades = Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereIn('student_id', $groups->keys())
            ->get(['course_id', 'student_id', 'score'])
            ->groupBy('student_id');

        $finals = [];

        foreach ($groups as $studentId => $groupId) {
            $own = $courses
                ->filter(fn (Course $course) => $course->group_id === null || $course->group_id === $groupId)
                ->pluck('id');

            $finals[$studentId] = BoletinService::average(
                ($grades->get($studentId) ?? collect())->whereIn('course_id', $own)->pluck('score')->all(),
            );
        }

        return $finals;
    }

    private function score(float|string|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    private function title(string $view, bool $year): string
    {
        return match (true) {
            $year => __('Definitivas del año'),
            $view === 'm1' => __('Notas del Módulo 1'),
            $view === 'm2' => __('Notas del Módulo 2'),
            default => __('Definitivas'),
        };
    }

    /**
     * "Ciclo 3 · 3A (2026-1) y 3B (2026-2)" para el año; si no, el ciclo y su
     * semestre.
     */
    private function scope(Cycle $cycle, ?Group $group, bool $year): string
    {
        $scope = $cycle->label;

        if ($year) {
            $previous = $cycle->previousCycle;
            preg_match('/\d+/', $cycle->level, $number);

            $scope = __(':name · :first (:firstTerm) y :second (:secondTerm)', [
                'name' => 'Ciclo '.($number[0] ?? $cycle->level),
                'first' => $previous->level,
                'firstTerm' => "{$previous->year}-{$previous->semester}",
                'second' => $cycle->level,
                'secondTerm' => "{$cycle->year}-{$cycle->semester}",
            ]);
        }

        return $group ? "{$scope} · {$group->name}" : $scope;
    }

    private function date(?CarbonInterface $date): string
    {
        return $date?->translatedFormat('j \d\e F \d\e Y') ?? '';
    }
}
