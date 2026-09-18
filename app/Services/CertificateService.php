<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Grade;
use App\Models\Student;

class CertificateService
{
    /**
     * Official certificate areas in order, each built from one or more subject codes.
     *
     * @var list<array{name: string, subjects: list<string>}>
     */
    private const OFFICIAL_AREAS = [
        ['name' => 'Ciencias Naturales', 'subjects' => ['CN']],
        ['name' => 'Ciencias Naturales - Física / Química', 'subjects' => ['FIS', 'QUI']],
        ['name' => 'Ciencias Sociales', 'subjects' => ['CS']],
        ['name' => 'Humanidades – Lengua Castellana / Idioma Inglés', 'subjects' => ['LEN', 'ING']],
        ['name' => 'Educación Artística', 'subjects' => ['EA']],
        ['name' => 'Educación Ética y Religiosa', 'subjects' => ['ER']],
        ['name' => 'Educación Ética y Religiosa: Proyecto de vida', 'subjects' => ['PV']],
        ['name' => 'Educación Física, Recreación y Deportes', 'subjects' => ['EF']],
        ['name' => 'Matemáticas', 'subjects' => ['MAT']],
        ['name' => 'Tecnología e Informática', 'subjects' => ['TEC']],
        ['name' => 'Filosofía', 'subjects' => ['FIL']],
    ];

    /**
     * Promedio a partir del cual se aprueba el ciclo arrastrando una materia
     * perdida. Con dos o más no hay promedio que valga.
     */
    public const PASSING_WITH_ONE_FAILED = 4.0;

    private const ROMAN = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];

    /**
     * @return array{
     *     student: Student,
     *     cycle: Cycle,
     *     group: ?string,
     *     grade_label: string,
     *     year: int,
     *     lines: list<array{name: string, cal: ?float, performance: ?string}>,
     *     average: ?float,
     *     average_performance: ?string,
     *     approved: bool,
     *     failed_subjects: list<string>,
     *     verb: string
     * }
     */
    public function generate(Student $student, Cycle $cycle, bool $bothSemesters = false): array
    {
        $previous = $bothSemesters ? $cycle->previousCycle : null;
        $both = $previous !== null;

        $sem1 = $both ? $this->subjectFinals($student, $previous) : [];
        $sem2 = $this->subjectFinals($student, $cycle);

        $lines = $both
            ? $this->buildAreasCombined($sem1, $sem2)
            : $this->buildAreas($sem2);

        $average = $this->average(array_column($lines, 'cal'));
        $failed = $this->failedSubjects($sem1, $sem2);

        $approved = match (true) {
            // Sin notas no hay nada que certificar.
            $average === null => false,
            $average < BoletinService::PASSING_SCORE => false,
            $failed === [] => true,
            // Una materia perdida se arrastra, pero solo con un buen promedio.
            count($failed) === 1 => $average >= self::PASSING_WITH_ONE_FAILED,
            default => false,
        };

        return [
            'student' => $student,
            'cycle' => $cycle,
            'previous' => $previous,
            'both' => $both,
            'group' => $student->enrollments()->with('group')->where('cycle_id', $cycle->id)->first()?->group?->name,
            'grade_label' => $this->gradeLabel($cycle, ! $both),
            'year' => $cycle->year,
            'year_label' => (string) $cycle->year,
            'lines' => $lines,
            'average' => $average,
            'average_performance' => BoletinService::performance($average),
            'approved' => $approved,
            'failed_subjects' => $failed,
            'verb' => $approved ? 'Cursó y Aprobó' : 'Cursó y No Aprobó',
        ];
    }

    /**
     * Las asignaturas por debajo del mínimo.
     *
     * Se mira asignatura por asignatura y no por el área que sale impresa: un
     * área junta dos materias —Humanidades es Lengua e Inglés— y el promedio de
     * las dos puede tapar una perdida.
     *
     * Una asignatura sin nota no cuenta como perdida: no se calificó, y eso se
     * arregla poniendo la nota, no reprobando a nadie. Solo se revisan las que
     * componen las áreas del certificado, que son las que el documento afirma.
     *
     * @param  array<string, ?float>  $sem1
     * @param  array<string, ?float>  $sem2
     * @return list<string>
     */
    private function failedSubjects(array $sem1, array $sem2): array
    {
        $official = array_flip(array_merge(...array_column(self::OFFICIAL_AREAS, 'subjects')));

        $failed = [];

        foreach (array_keys(array_intersect_key($sem1 + $sem2, $official)) as $code) {
            $final = $this->average([$sem1[$code] ?? null, $sem2[$code] ?? null]);

            if ($final !== null && $final < BoletinService::PASSING_SCORE) {
                $failed[] = $code;
            }
        }

        // El orden de los cursos lo pone la base de datos; ordenarlas deja la
        // lista igual de una ejecución a otra.
        sort($failed);

        return $failed;
    }

    /**
     * @return array<string, ?float> subject code => final score
     */
    private function subjectFinals(Student $student, Cycle $cycle): array
    {
        $enrollment = $student->enrollments()->where('cycle_id', $cycle->id)->first();
        $groupId = $enrollment?->group_id;

        $courses = Course::query()
            ->where('cycle_id', $cycle->id)
            ->where(fn ($query) => $query->whereNull('group_id')->orWhere('group_id', $groupId))
            ->with('subject')
            ->get();

        $scores = Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('course_id', $courses->pluck('id'))
            ->pluck('score', 'course_id');

        $periods = [];

        foreach ($courses as $course) {
            $periods[$course->subject->code][] = $scores[$course->id] ?? null;
        }

        $finals = [];

        foreach ($periods as $code => $values) {
            $finals[$code] = $this->average($values);
        }

        return $finals;
    }

    /**
     * @param  array<string, ?float>  $finals
     * @return list<array{name: string, cal: ?float, performance: ?string}>
     */
    private function buildAreas(array $finals): array
    {
        $lines = [];

        foreach (self::OFFICIAL_AREAS as $area) {
            $present = array_intersect_key($finals, array_flip($area['subjects']));

            if ($present === []) {
                continue;
            }

            $cal = $this->average(array_values($present));

            $lines[] = [
                'name' => $area['name'],
                'cal' => $cal,
                'performance' => BoletinService::performance($cal),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, ?float>  $sem1
     * @param  array<string, ?float>  $sem2
     * @return list<array{name: string, sem1: ?float, sem2: ?float, cal: ?float, performance: ?string}>
     */
    private function buildAreasCombined(array $sem1, array $sem2): array
    {
        $lines = [];

        foreach (self::OFFICIAL_AREAS as $area) {
            $keys = array_flip($area['subjects']);
            $s1 = $this->average(array_values(array_intersect_key($sem1, $keys)));
            $s2 = $this->average(array_values(array_intersect_key($sem2, $keys)));

            if ($s1 === null && $s2 === null) {
                continue;
            }

            $cal = $this->average([$s1, $s2]);

            $lines[] = [
                'name' => $area['name'],
                'sem1' => $s1,
                'sem2' => $s2,
                'cal' => $cal,
                'performance' => BoletinService::performance($cal),
            ];
        }

        return $lines;
    }

    /**
     * @param  list<float|string|null>  $values
     */
    private function average(array $values): ?float
    {
        $numbers = array_filter($values, fn ($value) => $value !== null);

        if ($numbers === []) {
            return null;
        }

        return round(array_sum($numbers) / count($numbers), 2);
    }

    private function gradeLabel(Cycle $cycle, bool $includeLetter = true): string
    {
        preg_match('/(\d+)\s*([A-Za-z])?/', $cycle->level, $matches);

        $roman = self::ROMAN[(int) ($matches[1] ?? 0)] ?? $cycle->level;
        $letter = $includeLetter && isset($matches[2]) && $matches[2] !== '' ? '-'.strtoupper($matches[2]) : '';

        return 'CICLO '.$roman.$letter;
    }
}
