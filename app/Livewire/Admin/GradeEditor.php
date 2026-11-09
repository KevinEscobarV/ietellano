<?php

namespace App\Livewire\Admin;

use App\Concerns\ShowsTutorials;
use App\Models\Course;
use App\Models\Cycle;
use App\Services\GradeProgressService;
use App\Services\TermService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Por dónde entra la coordinación a las notas: año y semestre, luego el
 * ciclo, luego la materia. Cada nivel muestra cuánto falta, para que se
 * llegue a la planilla sabiendo ya qué está atrasado. La planilla en sí es
 * CourseGrades, la misma del docente.
 */
#[Title('Editar notas')]
class GradeEditor extends Component
{
    use ShowsTutorials;

    #[Url(as: 'anio', except: '')]
    public string $year = '';

    #[Url(as: 'semestre', except: '')]
    public string $semester = '';

    #[Url(as: 'ciclo', except: '')]
    public string $cycleId = '';

    #[Url(as: 'modulo', except: '')]
    public string $period = '';

    #[Url(as: 'grupo', except: '')]
    public string $groupId = '';

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    #[Url(as: 'estado', except: '')]
    public string $status = '';

    public function mount(): void
    {
        // Un enlace que ya trae el ciclo manda sobre el año y el semestre.
        $cycle = $this->cycleId !== '' ? Cycle::find((int) $this->cycleId) : null;

        if ($cycle !== null) {
            $this->year = (string) $cycle->year;
            $this->semester = (string) $cycle->semester;

            return;
        }

        $terms = $this->terms();

        if (! $terms->has((int) $this->year)) {
            $this->year = (string) ($terms->keys()->first() ?? '');
        }

        $this->pickSemester();
    }

    public function updatedYear(): void
    {
        $this->pickSemester();
    }

    public function updatedSemester(): void
    {
        $this->pickCycle();
    }

    public function updatedCycleId(): void
    {
        $this->groupId = '';
    }

    public function clearFilters(): void
    {
        $this->reset(['period', 'groupId', 'search', 'status']);
    }

    /**
     * El semestre más reciente del año, salvo que el elegido también exista.
     */
    private function pickSemester(): void
    {
        $semesters = $this->terms()->get((int) $this->year, collect());

        if (! $semesters->contains((int) $this->semester)) {
            $this->semester = (string) ($semesters->first() ?? '');
        }

        $this->pickCycle();
    }

    /**
     * Se entra siempre con un ciclo elegido: así la lista de materias aparece
     * de una vez y las tarjetas de arriba sirven para cambiar.
     */
    private function pickCycle(): void
    {
        $this->cycleId = (string) ($this->termCycles()->first()?->id ?? '');
        $this->groupId = '';
    }

    /**
     * Año => semestres que tienen ciclos, lo más reciente primero.
     *
     * @return Collection<int, Collection<int, int>>
     */
    private function terms(): Collection
    {
        return Cycle::query()
            ->select('year', 'semester')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->get()
            ->groupBy('year')
            ->map(fn (Collection $rows) => $rows->pluck('semester')->map(fn ($semester) => (int) $semester)->values());
    }

    /**
     * @return Collection<int, Cycle>
     */
    private function termCycles(): Collection
    {
        if ($this->year === '' || $this->semester === '') {
            return collect();
        }

        return Cycle::query()
            ->where('year', (int) $this->year)
            ->where('semester', (int) $this->semester)
            ->ordered()
            ->get();
    }

    /**
     * @param  array{students: int, graded: int, pending: int, average: float|null}  $progress
     * @return array<string, mixed>
     */
    private function card(Course $course, array $progress, TermService $term): array
    {
        ['students' => $students, 'graded' => $graded, 'pending' => $pending] = $progress;

        return [
            'id' => $course->id,
            'cycle_id' => $course->cycle_id,
            'code' => $course->code,
            'subject' => $course->subject?->name ?? $course->code,
            'group' => $course->group?->name,
            'group_id' => $course->group_id,
            'period' => $course->period,
            'teacher' => $course->teacher?->name,
            'students' => $students,
            'graded' => $graded,
            'pending' => $pending,
            'average' => $progress['average'],
            'percent' => $students > 0 ? (int) round($graded / $students * 100) : 0,
            'status' => match (true) {
                $students === 0 => 'vacio',
                $pending === 0 => 'completo',
                $graded === 0 => 'pendiente',
                default => 'parcial',
            },
            // Si el docente todavía puede escribir: la coordinación siempre puede.
            'teacher_open' => $term->isOpen($course),
            'teacher_until' => $term->openUntil($course),
        ];
    }

    /**
     * Todo menos el estado, que se aplica aparte para poder contar cuántos
     * cursos caen en cada opción.
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $cards): Collection
    {
        $needle = $this->fold($this->search);

        return $cards
            ->when($this->period !== '', fn (Collection $c) => $c->where('period', (int) $this->period))
            // Un curso sin grupo es de todo el ciclo: también es del grupo elegido.
            ->when($this->groupId !== '', fn (Collection $c) => $c->filter(
                fn (array $card) => $card['group_id'] === null || $card['group_id'] === (int) $this->groupId,
            ))
            ->when($needle !== '', fn (Collection $c) => $c->filter(fn (array $card) => str_contains(
                $this->fold(implode(' ', [$card['subject'], $card['teacher'] ?? '', $card['group'] ?? '', $card['code']])),
                $needle,
            )));
    }

    /**
     * Sin tildes ni mayúsculas: "matematicas" tiene que encontrar "Matemáticas".
     */
    private function fold(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }

    /**
     * @return list<string>
     */
    protected function tutorials(): array
    {
        return ['editor-notas'];
    }

    public function render(TermService $term, GradeProgressService $progressService): View
    {
        $terms = $this->terms();
        $cycles = $this->termCycles();

        $courses = Course::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->with('subject', 'group', 'teacher')
            ->get();

        $students = $progressService->rosterSizes($cycles->pluck('id')->all())['by_cycle'];
        $progress = $progressService->forCourses($courses);
        $cards = $courses->map(fn (Course $course) => $this->card($course, $progress[$course->id], $term));

        $cycleCards = $cycles->map(function (Cycle $cycle) use ($cards, $students, $term) {
            $own = $cards->where('cycle_id', $cycle->id);
            $expected = $own->sum('students');
            $graded = $own->sum('graded');

            return [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'students' => $students[$cycle->id] ?? 0,
                'courses' => $own->count(),
                'graded' => $graded,
                'expected' => $expected,
                'percent' => $expected > 0 ? (int) round($graded / $expected * 100) : 0,
                // Cada módulo por su lado: el 1 puede estar cerrado mientras el 2 se califica.
                'modules' => collect(Cycle::MODULES)->mapWithKeys(fn (int $period) => [$period => $term->moduleStatus($cycle, $period)]),
            ];
        });

        $selected = $cycles->firstWhere('id', (int) $this->cycleId);
        $base = $selected ? $this->applyFilters($cards->where('cycle_id', $selected->id)) : collect();

        $visible = $base
            ->when($this->status === 'pendientes', fn (Collection $c) => $c->where('pending', '>', 0))
            ->when($this->status === 'completas', fn (Collection $c) => $c->filter(fn (array $card) => $card['students'] > 0 && $card['pending'] === 0))
            ->sortBy([['subject', 'asc'], ['group', 'asc']])
            ->groupBy('period')
            ->sortKeys();

        return view('livewire.admin.grade-editor', [
            'years' => $terms->keys(),
            'semesters' => $terms->get((int) $this->year, collect()),
            'cycleCards' => $cycleCards,
            'selected' => $selected,
            'groups' => $selected ? $selected->groups()->orderBy('name')->get() : collect(),
            'modules' => $selected ? $cards->where('cycle_id', $selected->id)->pluck('period')->unique()->sort()->values() : collect(),
            'byModule' => $visible,
            'counts' => [
                'all' => $base->count(),
                'pending' => $base->where('pending', '>', 0)->count(),
                'complete' => $base->filter(fn (array $card) => $card['students'] > 0 && $card['pending'] === 0)->count(),
            ],
            'filtering' => $this->period !== '' || $this->groupId !== '' || $this->search !== '' || $this->status !== '',
            'startTour' => $this->shouldStartTutorial('editor-notas'),
        ]);
    }
}
