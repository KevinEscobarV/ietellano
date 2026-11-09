<?php

namespace App\Concerns;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Services\TermService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Para las pantallas que se recorren año → semestre → ciclo → estudiante,
 * como Boletines y Certificados. Todo va en la URL: un enlace o un recargar
 * vuelven al mismo estudiante.
 */
trait BrowsesStudentsByTerm
{
    use BrowsesCyclesByTerm;

    #[Url(as: 'grupo', except: '')]
    public string $groupId = '';

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    #[Url(as: 'estudiante', except: '')]
    public string $studentId = '';

    public function clearSearch(): void
    {
        $this->reset(['search', 'groupId']);
    }

    /**
     * Cambiar de ciclo es empezar de nuevo: el estudiante, el grupo y lo
     * buscado eran del otro.
     */
    protected function resetCycleSelection(): void
    {
        $this->resetStudent();
    }

    /**
     * Lo que se borra al cambiar de ciclo. La pantalla le suma lo suyo, como
     * la opción de incluir el semestre anterior.
     */
    protected function resetStudent(): void
    {
        $this->reset(['studentId', 'groupId', 'search']);
    }

    /**
     * Los matriculados del ciclo, con cuántas de sus materias ya tienen nota.
     * Una materia sin grupo es de todo el ciclo; con grupo, solo de ese.
     *
     * @return Collection<int, array{id: int, name: string, document: string|null, group: string|null, group_id: int|null, graded: int, expected: int}>
     */
    private function roster(Cycle $cycle): Collection
    {
        $courses = Course::where('cycle_id', $cycle->id)->get(['id', 'group_id']);

        $scored = Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereNotNull('score')
            ->get(['student_id', 'course_id'])
            ->groupBy('student_id')
            ->map(fn (Collection $grades) => $grades->pluck('course_id'));

        return Enrollment::query()
            ->where('cycle_id', $cycle->id)
            ->with('student', 'group')
            ->get()
            ->filter(fn (Enrollment $enrollment) => $enrollment->student !== null)
            ->map(function (Enrollment $enrollment) use ($courses, $scored) {
                $own = $courses
                    ->filter(fn (Course $course) => $course->group_id === null || $course->group_id === $enrollment->group_id)
                    ->pluck('id');

                return [
                    'id' => $enrollment->student_id,
                    'name' => trim("{$enrollment->student->last_name} {$enrollment->student->first_name}"),
                    'document' => $enrollment->student->document,
                    'group' => $enrollment->group?->name,
                    'group_id' => $enrollment->group_id,
                    'graded' => ($scored->get($enrollment->student_id) ?? collect())->intersect($own)->count(),
                    'expected' => $own->count(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Por grupo y por nombre o documento, sin tildes ni mayúsculas.
     *
     * @param  Collection<int, array<string, mixed>>  $roster
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRoster(Collection $roster): Collection
    {
        $needle = Str::lower(Str::ascii(trim($this->search)));

        return $roster
            ->when($this->groupId !== '', fn (Collection $rows) => $rows->where('group_id', (int) $this->groupId))
            ->when($needle !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => str_contains(
                Str::lower(Str::ascii($row['name'].' '.($row['document'] ?? ''))),
                $needle,
            )))
            ->values();
    }

    /**
     * El estudiante elegido, siempre que esté matriculado en el ciclo.
     */
    private function selectedStudent(?Cycle $cycle): ?Student
    {
        if ($cycle === null || $this->studentId === '') {
            return null;
        }

        return Student::whereKey((int) $this->studentId)
            ->whereRelation('enrollments', 'cycle_id', $cycle->id)
            ->first();
    }

    /**
     * Lo que pintan los pasos 1 a 3, igual en las dos pantallas.
     *
     * @return array<string, mixed>
     */
    private function browserData(TermService $term): array
    {
        $data = $this->cycleBrowserData($term);
        $selected = $data['selected'];
        $roster = $selected ? $this->roster($selected) : collect();

        return [
            ...$data,
            'groups' => $roster->whereNotNull('group_id')->unique('group_id')->sortBy('group')->pluck('group', 'group_id'),
            'roster' => $this->filterRoster($roster),
            'rosterTotal' => $roster->count(),
            'student' => $this->selectedStudent($selected),
        ];
    }
}
