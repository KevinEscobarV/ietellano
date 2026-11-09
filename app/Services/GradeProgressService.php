<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Support\Collection;

/**
 * Cuántas notas lleva cada materia frente a cuántas le tocan. Un curso sin
 * grupo es de todo el ciclo; uno con grupo, solo de los estudiantes de ese
 * grupo. Son dos consultas sin importar cuántos cursos se pidan.
 */
class GradeProgressService
{
    /**
     * @param  Collection<int, Course>  $courses
     * @return array<int, array{students: int, graded: int, pending: int, average: float|null}>
     */
    public function forCourses(Collection $courses): array
    {
        if ($courses->isEmpty()) {
            return [];
        }

        $roster = $this->rosterSizes($courses->pluck('cycle_id')->unique()->values()->all());
        $grades = $this->gradeCounts($courses->pluck('id')->all());

        return $courses->mapWithKeys(function (Course $course) use ($roster, $grades) {
            $students = $course->group_id !== null
                ? ($roster['by_group']["{$course->cycle_id}:{$course->group_id}"] ?? 0)
                : ($roster['by_cycle'][$course->cycle_id] ?? 0);

            // Una nota de alguien que ya no está matriculado no tapa a otro que falte.
            $graded = min($grades[$course->id]['graded'] ?? 0, $students);

            return [$course->id => [
                'students' => $students,
                'graded' => $graded,
                'pending' => $students - $graded,
                'average' => $grades[$course->id]['average'] ?? null,
            ]];
        })->all();
    }

    /**
     * @param  list<int>  $cycleIds
     * @return array{by_cycle: array<int, int>, by_group: array<string, int>}
     */
    public function rosterSizes(array $cycleIds): array
    {
        $byCycle = [];
        $byGroup = [];

        $rows = Enrollment::query()
            ->whereIn('cycle_id', $cycleIds)
            ->selectRaw('cycle_id, group_id, count(*) as total')
            ->groupBy('cycle_id', 'group_id')
            ->get();

        foreach ($rows as $row) {
            $byCycle[$row->cycle_id] = ($byCycle[$row->cycle_id] ?? 0) + (int) $row->total;

            if ($row->group_id !== null) {
                $byGroup["{$row->cycle_id}:{$row->group_id}"] = (int) $row->total;
            }
        }

        return ['by_cycle' => $byCycle, 'by_group' => $byGroup];
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, array{graded: int, average: float}>
     */
    private function gradeCounts(array $courseIds): array
    {
        return Grade::query()
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('score')
            ->selectRaw('course_id, count(*) as graded, avg(score) as average')
            ->groupBy('course_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->course_id => [
                'graded' => (int) $row->graded,
                'average' => round((float) $row->average, 2),
            ]])
            ->all();
    }
}
