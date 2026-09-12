<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\EditWindow;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cierre de semestre')]
class TermClosure extends Component
{
    public bool $showWindowModal = false;

    public bool $showConfirmModal = false;

    /**
     * Lo que está por confirmarse. Las tres acciones preguntan lo mismo —si de
     * verdad—, así que comparten un solo aviso.
     */
    public string $confirming = '';

    public ?int $confirmingId = null;

    public string $cycleId = '';

    /**
     * Materia a la que se le abre la ventana. Vacío significa todo el ciclo.
     */
    public string $courseId = '';

    public string $closesAt = '';

    public string $note = '';

    public function confirm(string $action, int $id): void
    {
        abort_unless(in_array($action, ['close', 'reopen', 'window'], true), 404);

        $this->confirming = $action;
        $this->confirmingId = $id;
        $this->showConfirmModal = true;
    }

    public function confirmed(): void
    {
        $id = $this->confirmingId;
        $action = $this->confirming;

        abort_if($id === null, 404);

        $this->showConfirmModal = false;
        $this->reset(['confirming', 'confirmingId']);

        match ($action) {
            'close' => $this->closeCycle($id),
            'reopen' => $this->reopenCycle($id),
            'window' => $this->closeWindow($id),
            default => abort(404),
        };
    }

    /**
     * A qué se refiere el aviso: el nombre del semestre o el de la materia. Es
     * lo que un confirm del navegador no podía decir.
     */
    private function confirmTarget(): string
    {
        if ($this->confirmingId === null) {
            return '';
        }

        if ($this->confirming === 'window') {
            $window = EditWindow::with('course.subject', 'cycle')->find($this->confirmingId);

            return $window === null
                ? ''
                : trim(($window->course?->subject?->name ?? __('Todo el semestre')).' · '.($window->cycle?->label ?? ''), ' ·');
        }

        return (string) (Cycle::find($this->confirmingId)?->label ?? '');
    }

    public function closeCycle(int $cycleId): void
    {
        $cycle = Cycle::findOrFail($cycleId);

        $cycle->update(['closed_at' => now()]);

        Flux::toast(variant: 'success', text: __(':cycle quedó cerrado.', ['cycle' => $cycle->label]));
    }

    public function reopenCycle(int $cycleId): void
    {
        $cycle = Cycle::findOrFail($cycleId);

        $cycle->update(['closed_at' => null]);

        Flux::toast(variant: 'success', text: __(':cycle vuelve a estar abierto.', ['cycle' => $cycle->label]));
    }

    /**
     * Se abre con un plazo por defecto de tres días a las seis de la tarde: lo
     * normal es que la corrección sea de esta semana, no de este mes.
     */
    public function openWindowForm(?int $cycleId = null): void
    {
        $this->reset(['courseId', 'note']);

        $this->cycleId = (string) ($cycleId ?? $this->cycleId ?: (Cycle::ordered()->value('id') ?? ''));
        $this->closesAt = now()->addDays(3)->setTime(18, 0)->format('Y-m-d\TH:i');

        $this->showWindowModal = true;
    }

    public function updatedCycleId(): void
    {
        $this->reset('courseId');
    }

    public function openWindow(): void
    {
        $validated = $this->validate([
            'cycleId' => ['required', 'exists:cycles,id'],
            'courseId' => ['nullable'],
            'closesAt' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'cycleId' => __('ciclo'),
            'closesAt' => __('fecha de cierre'),
            'note' => __('motivo'),
        ]);

        // La materia tiene que ser del ciclo elegido: si no, la ventana no
        // cubriría nada y el administrador se quedaría esperando.
        if ($this->courseId !== '' && ! Course::where('id', (int) $this->courseId)->where('cycle_id', (int) $this->cycleId)->exists()) {
            $this->addError('courseId', __('Esa materia no es de este ciclo.'));

            return;
        }

        EditWindow::create([
            'cycle_id' => (int) $validated['cycleId'],
            'course_id' => $this->courseId === '' ? null : (int) $this->courseId,
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
     * @return Collection<int, Course>
     */
    private function coursesOfCycle(): Collection
    {
        if ($this->cycleId === '') {
            return collect();
        }

        return Course::where('cycle_id', (int) $this->cycleId)
            ->with('subject', 'group', 'teacher')
            ->get()
            ->sortBy(fn (Course $course) => [$course->subject?->name ?? $course->code, $course->group?->name ?? '', $course->period])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.admin.closure', [
            'cycles' => Cycle::ordered()->withCount('courses')->get(),
            'courses' => $this->coursesOfCycle(),
            'confirmTarget' => $this->confirmTarget(),
            'windows' => EditWindow::query()
                ->with('cycle', 'course.subject', 'course.group', 'openedBy')
                ->orderByDesc('closes_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
