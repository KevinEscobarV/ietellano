<?php

namespace App\Livewire\Teacher;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Mis materias')]
class MyCourses extends Component
{
    #[Url(as: 'ciclo', except: '')]
    public string $cycleId = '';

    public string $search = '';

    /**
     * Un docente arrastra los cursos de los semestres anteriores. Abrir en
     * "todos" mezclaría el semestre cerrado con el que está en curso, así que
     * se entra por el más reciente y el filtro deja ver el resto.
     */
    public function mount(): void
    {
        if ($this->cycleId === '') {
            $this->cycleId = (string) (Cycle::query()
                ->whereHas('courses', fn ($query) => $query->where('teacher_id', $this->teacher()->id))
                ->ordered()
                ->value('id') ?? '');
        }
    }

    public function render(): View
    {
        $teacher = $this->teacher();

        $courses = Course::query()
            ->where('teacher_id', $teacher->id)
            ->with('subject', 'group', 'cycle')
            ->get();

        $cycles = $courses
            ->pluck('cycle')
            ->filter()
            ->unique('id')
            ->sortByDesc(fn (Cycle $cycle) => [$cycle->year, $cycle->semester, $cycle->level])
            ->values();

        $visible = $this->applyFilters($courses);

        $roster = $this->rosterSizes($visible);
        $progress = $this->gradeProgress($visible);

        $cards = $visible
            ->map(fn (Course $course) => $this->card($course, $roster, $progress))
            ->sortBy([
                fn (array $a, array $b) => $b['cycle_rank'] <=> $a['cycle_rank'],
                fn (array $a, array $b) => $a['subject'] <=> $b['subject'],
                fn (array $a, array $b) => $a['period'] <=> $b['period'],
            ])
            ->values();

        return view('livewire.teacher.my-courses', [
            'teacher' => $teacher,
            'cycles' => $cycles,
            'cards' => $cards,
            'summary' => $this->summary($cards, $this->uniqueStudents($visible)),
        ]);
    }

    private function teacher(): Teacher
    {
        // El middleware `teacher` ya garantizó que existe.
        return auth()->user()->teacher;
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, Course>
     */
    private function applyFilters(Collection $courses): Collection
    {
        $needle = mb_strtolower(trim($this->search));

        return $courses
            ->when($this->cycleId !== '', fn (Collection $c) => $c->where('cycle_id', (int) $this->cycleId))
            ->when($needle !== '', fn (Collection $c) => $c->filter(fn (Course $course) => str_contains(
                mb_strtolower(implode(' ', [
                    $course->subject?->name ?? '',
                    $course->group?->name ?? '',
                    $course->cycle?->label ?? '',
                ])),
                $needle,
            )))
            ->values();
    }

    /**
     * Cuántos estudiantes le corresponden a cada curso. Un curso sin grupo
     * cubre a todo el ciclo; con grupo, solo a ese grupo. Se resuelve con una
     * sola consulta agrupada en vez de una por curso.
     *
     * @param  Collection<int, Course>  $courses
     * @return array{by_cycle: array<int, int>, by_group: array<string, int>}
     */
    private function rosterSizes(Collection $courses): array
    {
        $byCycle = [];
        $byGroup = [];

        $rows = Enrollment::query()
            ->whereIn('cycle_id', $courses->pluck('cycle_id')->unique()->all())
            ->selectRaw('cycle_id, group_id, count(*) as total')
            ->groupBy('cycle_id', 'group_id')
            ->get();

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $byCycle[$row->cycle_id] = ($byCycle[$row->cycle_id] ?? 0) + $total;

            if ($row->group_id !== null) {
                $byGroup["{$row->cycle_id}:{$row->group_id}"] = $total;
            }
        }

        return ['by_cycle' => $byCycle, 'by_group' => $byGroup];
    }

    /**
     * Personas distintas a las que este docente les da clase. No es la suma de
     * los cursos: un mismo estudiante aparece en varias materias suyas y solo
     * cuenta una vez.
     *
     * @param  Collection<int, Course>  $courses
     */
    private function uniqueStudents(Collection $courses): int
    {
        $scopes = $courses
            ->map(fn (Course $course) => [$course->cycle_id, $course->group_id])
            ->unique(fn (array $scope) => $scope[0].':'.$scope[1]);

        if ($scopes->isEmpty()) {
            return 0;
        }

        return Enrollment::query()
            ->where(function ($query) use ($scopes) {
                foreach ($scopes as [$cycleId, $groupId]) {
                    $query->orWhere(fn ($scope) => $scope
                        ->where('cycle_id', $cycleId)
                        ->when($groupId, fn ($q) => $q->where('group_id', $groupId)));
                }
            })
            ->distinct()
            ->count('student_id');
    }

    /**
     * Notas ya puestas y promedio de cada curso, también en una sola consulta.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, array{graded: int, average: float}>
     */
    private function gradeProgress(Collection $courses): array
    {
        return Grade::query()
            ->whereIn('course_id', $courses->pluck('id')->all())
            ->whereNotNull('score')
            ->selectRaw('course_id, count(*) as graded, avg(score) as average')
            ->groupBy('course_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->course_id => [
                    'graded' => (int) $row->graded,
                    'average' => round((float) $row->average, 2),
                ],
            ])
            ->all();
    }

    /**
     * @param  array{by_cycle: array<int, int>, by_group: array<string, int>}  $roster
     * @param  array<int, array{graded: int, average: float}>  $progress
     * @return array<string, mixed>
     */
    private function card(Course $course, array $roster, array $progress): array
    {
        $students = $course->group_id !== null
            ? ($roster['by_group']["{$course->cycle_id}:{$course->group_id}"] ?? 0)
            : ($roster['by_cycle'][$course->cycle_id] ?? 0);

        $graded = $progress[$course->id]['graded'] ?? 0;
        $pending = max($students - $graded, 0);

        return [
            'id' => $course->id,
            'code' => $course->code,
            'subject' => $course->subject?->name ?? $course->code,
            'group' => $course->group?->name ?? '',
            'period' => $course->period,
            'cycle' => $course->cycle?->label ?? '',
            'cycle_id' => $course->cycle_id,
            'cycle_rank' => ($course->cycle?->year ?? 0) * 10 + ($course->cycle?->semester ?? 0),
            'students' => $students,
            'graded' => $graded,
            'pending' => $pending,
            'average' => $progress[$course->id]['average'] ?? null,
            'percent' => $students > 0 ? (int) round($graded / $students * 100) : 0,
            'status' => match (true) {
                $students === 0 => 'vacio',
                $pending === 0 => 'completo',
                $graded === 0 => 'pendiente',
                default => 'parcial',
            },
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return array{courses: int, students: int, pending: int, average: ?float}
     */
    private function summary(Collection $cards, int $students): array
    {
        $graded = $cards->sum('graded');

        return [
            'courses' => $cards->count(),
            'students' => $students,
            'pending' => $cards->sum('pending'),
            // Promedio ponderado por notas puestas: una materia con 30 notas
            // pesa más que una con 3.
            'average' => $graded > 0
                ? round($cards->sum(fn (array $c) => ($c['average'] ?? 0) * $c['graded']) / $graded, 2)
                : null,
        ];
    }
}
