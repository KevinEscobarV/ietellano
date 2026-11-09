<?php

namespace App\Livewire\Admin;

use App\Concerns\ShowsTutorials;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\EditWindow;
use App\Models\Enrollment;
use App\Models\Grade;
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
     * Cuántos estudiantes le tocan a cada curso, en una sola consulta: un
     * curso sin grupo cubre todo el ciclo y uno con grupo, solo ese grupo.
     *
     * @param  list<int>  $cycleIds
     * @return array{by_cycle: array<int, int>, by_group: array<string, int>}
     */
    private function rosterSizes(array $cycleIds): array
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
    private function gradeProgress(array $courseIds): array
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

    /**
     * @param  array{by_cycle: array<int, int>, by_group: array<string, int>}  $roster
     * @param  array<int, array{graded: int, average: float}>  $progress
     * @return array<string, mixed>
     */
    private function card(Course $course, array $roster, array $progress, TermService $term): array
    {
        $students = $course->group_id !== null
            ? ($roster['by_group']["{$course->cycle_id}:{$course->group_id}"] ?? 0)
            : ($roster['by_cycle'][$course->cycle_id] ?? 0);

        $graded = min($progress[$course->id]['graded'] ?? 0, $students);
        $pending = $students - $graded;

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
            'average' => $progress[$course->id]['average'] ?? null,
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

    public function render(TermService $term): View
    {
        $terms = $this->terms();
        $cycles = $this->termCycles();

        $courses = Course::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->with('subject', 'group', 'teacher')
            ->get();

        $roster = $this->rosterSizes($cycles->pluck('id')->all());
        $progress = $this->gradeProgress($courses->pluck('id')->all());
        $cards = $courses->map(fn (Course $course) => $this->card($course, $roster, $progress, $term));

        $windows = EditWindow::active()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->get()
            ->groupBy('cycle_id');

        $cycleCards = $cycles->map(function (Cycle $cycle) use ($cards, $roster, $windows) {
            $own = $cards->where('cycle_id', $cycle->id);
            $expected = $own->sum('students');
            $graded = $own->sum('graded');

            return [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'students' => $roster['by_cycle'][$cycle->id] ?? 0,
                'courses' => $own->count(),
                'graded' => $graded,
                'expected' => $expected,
                'percent' => $expected > 0 ? (int) round($graded / $expected * 100) : 0,
                'closed' => $cycle->isClosed(),
                'window_until' => $windows->get($cycle->id)?->max('closes_at'),
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
