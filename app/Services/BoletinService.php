<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Grade;
use App\Models\Student;

class BoletinService
{
    public const PASSING_SCORE = 3.0;

    /**
     * National performance scale (Decreto 1290). Cut points are institution-configurable.
     */
    public static function performance(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 4.6 => 'Superior',
            $score >= 4.0 => 'Alto',
            $score >= self::PASSING_SCORE => 'Básico',
            default => 'Bajo',
        };
    }

    /**
     * Build the report-card data for a student within a cycle. When
     * $bothSemesters is set and the cycle has a previous cycle, each line
     * carries the prior semester (sem1), current semester (sem2) and their
     * average (final).
     */
    public function generate(Student $student, Cycle $cycle, bool $bothSemesters = false): array
    {
        $enrollment = $student->enrollments()->with('group')->where('cycle_id', $cycle->id)->first();
        $previous = $bothSemesters ? $cycle->previousCycle : null;
        $both = $previous !== null;

        $current = $this->subjectData($student, $cycle, $enrollment?->group_id);

        if ($both) {
            $prevGroupId = $student->enrollments()->where('cycle_id', $previous->id)->value('group_id');
            $subjectLines = $this->combinedSubjectLines($current, $this->subjectData($student, $previous, $prevGroupId));
        } else {
            $subjectLines = $this->singleSubjectLines($current);
        }

        $lines = array_map(
            fn (array $line) => [...$line, 'performance' => self::performance($line['final'])],
            $this->buildLines($cycle, $subjectLines, $both),
        );

        $overall = $this->average(array_column($lines, 'final'));

        return [
            'student' => $student,
            'cycle' => $cycle,
            'cycle_label' => $this->cycleLabel($cycle, $both),
            'previous' => $previous,
            'both' => $both,
            'group' => $enrollment?->group?->name,
            'lines' => $lines,
            'overall' => $overall,
            'overall_performance' => self::performance($overall),
        ];
    }

    /**
     * @return array<int, array{name: string, teacher: ?string, periods: array<int, float|string|null>}>
     */
    private function subjectData(Student $student, Cycle $cycle, ?int $groupId): array
    {
        $courses = Course::query()
            ->where('cycle_id', $cycle->id)
            ->where(fn ($query) => $query->whereNull('group_id')->orWhere('group_id', $groupId))
            ->with('subject', 'teacher')
            ->get();

        $scores = Grade::query()
            ->where('student_id', $student->id)
            ->whereIn('course_id', $courses->pluck('id'))
            ->pluck('score', 'course_id');

        $subjects = [];

        foreach ($courses as $course) {
            $subjects[$course->subject_id]['name'] ??= $course->subject->name;
            $subjects[$course->subject_id]['teacher'] ??= $course->teacher?->name;
            $subjects[$course->subject_id]['periods'][$course->period] = $scores[$course->id] ?? null;
        }

        return $subjects;
    }

    /**
     * @param  array<int, array{name: string, teacher: ?string, periods: array<int, float|string|null>}>  $current
     * @return array<int, array{name: string, teacher: ?string, p1: ?float, p2: ?float, final: ?float}>
     */
    private function singleSubjectLines(array $current): array
    {
        $lines = [];

        foreach ($current as $subjectId => $data) {
            $p1 = $data['periods'][1] ?? null;
            $p2 = $data['periods'][2] ?? null;

            $lines[$subjectId] = [
                'name' => $data['name'],
                'teacher' => $data['teacher'] ?? null,
                'p1' => $this->toFloat($p1),
                'p2' => $this->toFloat($p2),
                'final' => $this->average([$p1, $p2]),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array{name: string, teacher: ?string, periods: array<int, float|string|null>}>  $current
     * @param  array<int, array{name: string, teacher: ?string, periods: array<int, float|string|null>}>  $previous
     * @return array<int, array{name: string, teacher: ?string, sem1: ?float, sem2: ?float, final: ?float}>
     */
    private function combinedSubjectLines(array $current, array $previous): array
    {
        $lines = [];

        foreach (array_keys($current + $previous) as $subjectId) {
            $sem1 = $this->average(array_values($previous[$subjectId]['periods'] ?? []));
            $sem2 = $this->average(array_values($current[$subjectId]['periods'] ?? []));

            $lines[$subjectId] = [
                'name' => $current[$subjectId]['name'] ?? $previous[$subjectId]['name'],
                'teacher' => $current[$subjectId]['teacher'] ?? $previous[$subjectId]['teacher'] ?? null,
                'sem1' => $sem1,
                'sem2' => $sem2,
                'final' => $this->average([$sem1, $sem2]),
            ];
        }

        return $lines;
    }

    /**
     * Collapse area subjects into a single line and keep the rest standalone.
     *
     * @param  array<int, array<string, mixed>>  $subjectLines
     * @return list<array<string, mixed>>
     */
    private function buildLines(Cycle $cycle, array $subjectLines, bool $both): array
    {
        $lines = [];
        $usedSubjectIds = [];

        foreach ($cycle->areas()->with('subjects')->orderBy('display_order')->get() as $area) {
            $members = array_intersect_key($subjectLines, array_flip($area->subjects->pluck('id')->all()));

            if ($members === []) {
                continue;
            }

            $usedSubjectIds = [...$usedSubjectIds, ...array_keys($members)];
            $lines[] = $this->areaLine($area->name, $members, $both);
        }

        foreach ($subjectLines as $subjectId => $subject) {
            if (in_array($subjectId, $usedSubjectIds, true)) {
                continue;
            }

            $lines[] = [...$subject, 'type' => 'subject', 'components' => []];
        }

        usort($lines, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @return array<string, mixed>
     */
    private function areaLine(string $name, array $members, bool $both): array
    {
        $line = [
            'name' => $name,
            'type' => 'area',
            'teacher' => null,
            'final' => $this->average(array_column($members, 'final')),
            'components' => array_values(array_map(
                fn (array $m) => $both
                    ? ['name' => $m['name'], 'teacher' => $m['teacher'] ?? null, 'sem1' => $m['sem1'], 'sem2' => $m['sem2'], 'final' => $m['final']]
                    : ['name' => $m['name'], 'teacher' => $m['teacher'] ?? null, 'final' => $m['final']],
                $members,
            )),
        ];

        if ($both) {
            $line['sem1'] = $this->average(array_column($members, 'sem1'));
            $line['sem2'] = $this->average(array_column($members, 'sem2'));
        } else {
            $line['p1'] = $this->average(array_column($members, 'p1'));
            $line['p2'] = $this->average(array_column($members, 'p2'));
        }

        return $line;
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

    private function toFloat(float|string|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    private function cycleLabel(Cycle $cycle, bool $both): string
    {
        if (! $both) {
            return $cycle->name;
        }

        preg_match('/(\d+)/', $cycle->level, $matches);

        return 'Ciclo '.($matches[1] ?? $cycle->level);
    }
}
