<?php

namespace App\Livewire\Admin;

use App\Concerns\ShowsTutorials;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\EditWindow;
use App\Services\GradeProgressService;
use App\Services\TermService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Donde la coordinación cierra las notas. Se cierra por módulo: el Módulo 1
 * queda en solo lectura mientras el 2 todavía se califica, y el semestre de un
 * ciclo está cerrado cuando lo están sus dos módulos. Lo normal es cerrar un
 * módulo en todos los ciclos del semestre a la vez; un ciclo que vaya a otro
 * ritmo se maneja por su lado.
 */
#[Title('Cierre de módulos')]
class TermClosure extends Component
{
    use ShowsTutorials;

    /**
     * El semestre que se está mirando, como "2026-2".
     */
    #[Url(as: 'semestre', except: '')]
    public string $term = '';

    public bool $showConfirmModal = false;

    /**
     * Lo que está por confirmarse: close, reopen o window. Las tres preguntan
     * lo mismo —si de verdad—, así que comparten un solo aviso.
     */
    public string $confirming = '';

    public ?int $confirmingPeriod = null;

    /**
     * El ciclo sobre el que se actúa. Vacío es todos los del semestre.
     */
    public ?int $confirmingCycle = null;

    public ?int $confirmingWindow = null;

    public bool $showScheduleModal = false;

    public ?int $schedulePeriod = null;

    public ?int $scheduleCycle = null;

    public string $scheduleAt = '';

    public bool $showWindowModal = false;

    public string $cycleId = '';

    /**
     * Módulo al que se le abre la ventana. Vacío significa los dos.
     */
    public string $windowPeriod = '';

    /**
     * Materia a la que se le abre la ventana. Vacío significa todo el módulo.
     */
    public string $courseId = '';

    public string $closesAt = '';

    public string $note = '';

    public function mount(): void
    {
        $terms = $this->terms();

        if (! $terms->contains($this->term)) {
            $this->term = (string) ($terms->first() ?? '');
        }
    }

    public function confirmModule(string $action, int $period, ?int $cycleId = null): void
    {
        abort_unless(in_array($action, ['close', 'reopen'], true), 404);
        $this->ensureModule($period);

        $this->confirming = $action;
        $this->confirmingPeriod = $period;
        $this->confirmingCycle = $cycleId;
        $this->confirmingWindow = null;
        $this->showConfirmModal = true;
    }

    public function confirmWindow(int $windowId): void
    {
        $this->confirming = 'window';
        $this->confirmingWindow = EditWindow::findOrFail($windowId)->id;
        $this->confirmingPeriod = null;
        $this->confirmingCycle = null;
        $this->showConfirmModal = true;
    }

    public function confirmed(): void
    {
        [$action, $period, $cycleId, $windowId] = [$this->confirming, $this->confirmingPeriod, $this->confirmingCycle, $this->confirmingWindow];

        $this->showConfirmModal = false;
        $this->reset(['confirming', 'confirmingPeriod', 'confirmingCycle', 'confirmingWindow']);

        match (true) {
            $action === 'close' && $period !== null => $this->closeModule($period, $cycleId),
            $action === 'reopen' && $period !== null => $this->reopenModule($period, $cycleId),
            $action === 'window' && $windowId !== null => $this->closeWindow($windowId),
            default => abort(404),
        };
    }

    public function closeModule(int $period, ?int $cycleId = null): void
    {
        $this->ensureModule($period);

        $cycles = $this->targetCycles($cycleId);
        $cycles->each(fn (Cycle $cycle) => $cycle->closeModule($period, auth()->id()));

        Flux::toast(variant: 'success', text: __('Módulo :n cerrado en :target.', ['n' => $period, 'target' => $this->targetLabel($cycles, $cycleId)]));
    }

    public function reopenModule(int $period, ?int $cycleId = null): void
    {
        $this->ensureModule($period);

        $cycles = $this->targetCycles($cycleId);
        $cycles->each(fn (Cycle $cycle) => $cycle->reopenModule($period));

        Flux::toast(variant: 'success', text: __('Módulo :n abierto otra vez en :target.', ['n' => $period, 'target' => $this->targetLabel($cycles, $cycleId)]));
    }

    /**
     * Abre el formulario con la fecha que ya tenía programada, o con una
     * semana de plazo a las seis de la tarde.
     */
    public function openScheduleForm(int $period, ?int $cycleId = null): void
    {
        $this->ensureModule($period);

        $current = $this->targetCycles($cycleId)
            ->map(fn (Cycle $cycle) => $cycle->closureFor($period))
            ->filter(fn ($closure) => $closure?->isScheduled())
            ->max('closes_at');

        $this->schedulePeriod = $period;
        $this->scheduleCycle = $cycleId;
        $this->scheduleAt = ($current ?? now()->addWeek()->setTime(18, 0))->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->showScheduleModal = true;
    }

    /**
     * Programa el cierre en los ciclos donde el módulo sigue abierto. Uno ya
     * cerrado no se toca: reabrirlo hasta una fecha es lo que hace una
     * ventana.
     */
    public function scheduleClosure(): void
    {
        $this->validate(
            ['scheduleAt' => ['required', 'date', 'after:now']],
            attributes: ['scheduleAt' => __('fecha de cierre')],
        );

        abort_if($this->schedulePeriod === null, 404);
        $this->ensureModule($this->schedulePeriod);

        $closesAt = Carbon::parse($this->scheduleAt);
        $period = $this->schedulePeriod;

        $this->targetCycles($this->scheduleCycle)
            ->reject(fn (Cycle $cycle) => $cycle->isModuleClosed($period))
            ->each(fn (Cycle $cycle) => $cycle->scheduleModuleClosure($period, $closesAt, auth()->id()));

        $this->showScheduleModal = false;
        $this->reset(['schedulePeriod', 'scheduleCycle', 'scheduleAt']);

        Flux::toast(variant: 'success', text: __('Listo: el Módulo :n se cierra solo el :date', ['n' => $period, 'date' => $closesAt->translatedFormat('j \d\e F, g:i a')]));
    }

    /**
     * Se abre con un plazo por defecto de tres días a las seis de la tarde: lo
     * normal es que la corrección sea de esta semana, no de este mes.
     */
    public function openWindowForm(?int $cycleId = null, ?int $period = null): void
    {
        $this->reset(['courseId', 'note']);
        $this->resetValidation();

        $this->cycleId = (string) ($cycleId ?? $this->termCycles()->first()?->id ?? '');
        $this->windowPeriod = $period !== null ? (string) $period : '';
        $this->closesAt = now()->addDays(3)->setTime(18, 0)->format('Y-m-d\TH:i');

        $this->showWindowModal = true;
    }

    public function updatedCycleId(): void
    {
        $this->reset('courseId');
    }

    public function updatedWindowPeriod(): void
    {
        $this->reset('courseId');
    }

    public function openWindow(): void
    {
        $validated = $this->validate([
            'cycleId' => ['required', 'exists:cycles,id'],
            'windowPeriod' => ['nullable', 'in:'.implode(',', Cycle::MODULES)],
            'courseId' => ['nullable'],
            'closesAt' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'cycleId' => __('ciclo'),
            'windowPeriod' => __('módulo'),
            'closesAt' => __('fecha de cierre'),
            'note' => __('motivo'),
        ]);

        $course = null;

        // La materia tiene que ser del ciclo elegido: si no, la ventana no
        // cubriría nada y el administrador se quedaría esperando.
        if ($this->courseId !== '') {
            $course = Course::where('id', (int) $this->courseId)->where('cycle_id', (int) $this->cycleId)->first();

            if ($course === null) {
                $this->addError('courseId', __('Esa materia no es de este ciclo.'));

                return;
            }
        }

        EditWindow::create([
            'cycle_id' => (int) $validated['cycleId'],
            'course_id' => $course?->id,
            'period' => $course?->period ?? ($this->windowPeriod === '' ? null : (int) $this->windowPeriod),
            'closes_at' => $validated['closesAt'],
            'note' => $validated['note'] ?: null,
            'opened_by' => auth()->id(),
        ]);

        $this->showWindowModal = false;
        $this->reset(['courseId', 'note']);

        Flux::toast(variant: 'success', text: __('Ventana abierta.'));
    }

    /**
     * Cerrarla antes de tiempo es adelantarle el vencimiento, no borrarla: la
     * ventana queda como constancia de que hubo una corrección autorizada.
     */
    public function closeWindow(int $id): void
    {
        EditWindow::findOrFail($id)->update(['closes_at' => now()]);

        Flux::toast(variant: 'success', text: __('Ventana cerrada.'));
    }

    /**
     * @return list<string>
     */
    protected function tutorials(): array
    {
        return ['cierre'];
    }

    private function ensureModule(int $period): void
    {
        abort_unless(in_array($period, Cycle::MODULES, true), 404);
    }

    /**
     * Los semestres que tienen ciclos, como "2026-2", el más reciente primero.
     *
     * @return Collection<int, string>
     */
    private function terms(): Collection
    {
        return Cycle::query()
            ->select('year', 'semester')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->get()
            ->map(fn (Cycle $cycle) => "{$cycle->year}-{$cycle->semester}")
            ->values();
    }

    /**
     * @return Collection<int, Cycle>
     */
    private function termCycles(): Collection
    {
        [$year, $semester] = array_pad(explode('-', $this->term, 2), 2, '');

        if ($year === '' || $semester === '') {
            return collect();
        }

        return Cycle::query()
            ->where('year', (int) $year)
            ->where('semester', (int) $semester)
            ->ordered()
            ->with('moduleClosures')
            ->get();
    }

    /**
     * Un ciclo, o todos los del semestre que se está mirando.
     *
     * @return Collection<int, Cycle>
     */
    private function targetCycles(?int $cycleId): Collection
    {
        return $cycleId !== null
            ? collect([Cycle::with('moduleClosures')->findOrFail($cycleId)])
            : $this->termCycles();
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     */
    private function targetLabel(Collection $cycles, ?int $cycleId): string
    {
        return $cycleId !== null
            ? (string) $cycles->first()?->name
            : trans_choice('{0}ningún ciclo|{1}el único ciclo del semestre|[2,*]los :count ciclos del semestre', $cycles->count(), ['count' => $cycles->count()]);
    }

    /**
     * Cuántas materias y notas caen en un módulo de un ciclo.
     *
     * @param  Collection<int, Course>  $courses
     * @param  array<int, array{students: int, graded: int, pending: int, average: float|null}>  $progress
     * @return array{courses: int, expected: int, graded: int, pending: int}
     */
    private function tally(Collection $courses, array $progress): array
    {
        $own = $courses->map(fn (Course $course) => $progress[$course->id]);

        return [
            'courses' => $courses->count(),
            'expected' => $own->sum('students'),
            'graded' => $own->sum('graded'),
            'pending' => $own->sum('pending'),
        ];
    }

    /**
     * Lo que dice el aviso de confirmación: sobre qué se actúa y, al cerrar,
     * cuántas notas siguen sin poner. Es lo que un confirm del navegador no
     * podía decir.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{target: string, pending: int}
     */
    private function confirmContext(Collection $rows): array
    {
        if ($this->confirming === 'window') {
            $window = $this->confirmingWindow !== null
                ? EditWindow::with('course.subject', 'cycle')->find($this->confirmingWindow)
                : null;

            return [
                'target' => $window === null ? '' : trim(($window->course?->subject?->name ?? __('Todo el ciclo')).' · '.($window->cycle?->label ?? ''), ' ·'),
                'pending' => 0,
            ];
        }

        if ($this->confirmingPeriod === null) {
            return ['target' => '', 'pending' => 0];
        }

        $targeted = $this->confirmingCycle !== null
            ? $rows->where('cycle.id', $this->confirmingCycle)
            : $rows;

        $names = $this->confirmingCycle !== null
            ? (string) $targeted->first()['cycle']->name
            : $targeted->pluck('cycle.name')->implode(', ');

        return [
            'target' => __('Módulo :n', ['n' => $this->confirmingPeriod]).' · '.$names,
            'pending' => $targeted->sum(fn (array $row) => $row['modules'][$this->confirmingPeriod]['pending']),
        ];
    }

    public function render(TermService $term, GradeProgressService $progressService): View
    {
        $cycles = $this->termCycles();

        $courses = Course::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->with('subject', 'group', 'teacher')
            ->get();

        $progress = $progressService->forCourses($courses);

        $rows = $cycles->map(fn (Cycle $cycle) => [
            'cycle' => $cycle,
            'closed' => $term->isClosed($cycle),
            'modules' => collect(Cycle::MODULES)->mapWithKeys(fn (int $period) => [$period => [
                ...$term->moduleStatus($cycle, $period),
                ...$this->tally($courses->where('cycle_id', $cycle->id)->where('period', $period), $progress),
            ]])->all(),
        ]);

        // El mismo módulo visto en todos los ciclos del semestre: si todos están
        // igual se dice una sola vez; si no, cuántos van cerrados.
        $modules = collect(Cycle::MODULES)->mapWithKeys(function (int $period) use ($rows) {
            $cells = $rows->map(fn (array $row) => $row['modules'][$period]);
            $states = $cells->pluck('state')->unique();

            return [$period => [
                'state' => $states->count() === 1 ? $states->first() : 'mixed',
                'at' => $cells->pluck('at')->filter()->max(),
                'cycles' => $cells->count(),
                'closed' => $cells->where('state', 'closed')->count(),
                'courses' => $cells->sum('courses'),
                'expected' => $cells->sum('expected'),
                'graded' => $cells->sum('graded'),
                'pending' => $cells->sum('pending'),
            ]];
        });

        $windows = EditWindow::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->with('cycle', 'course.subject', 'course.group', 'openedBy')
            ->orderByDesc('closes_at')
            ->get();

        $windowCourses = $this->cycleId === ''
            ? collect()
            : $courses->where('cycle_id', (int) $this->cycleId)
                ->when($this->windowPeriod !== '', fn (Collection $c) => $c->where('period', (int) $this->windowPeriod))
                ->sortBy(fn (Course $course) => [$course->period, $course->subject?->name ?? $course->code, $course->group?->name ?? ''])
                ->values();

        return view('livewire.admin.closure', [
            'terms' => $this->terms(),
            'rows' => $rows,
            'modules' => $modules,
            'active' => $windows->filter->isActive()->values(),
            // Las vencidas son constancia, no algo por hacer: basta con las últimas.
            'expired' => $windows->reject->isActive()->take(10)->values(),
            'expiredCount' => $windows->reject->isActive()->count(),
            'windowCycles' => $cycles,
            'windowCourses' => $windowCourses,
            'confirm' => $this->showConfirmModal ? $this->confirmContext($rows) : ['target' => '', 'pending' => 0],
            'startTour' => $this->shouldStartTutorial('cierre'),
        ]);
    }
}
