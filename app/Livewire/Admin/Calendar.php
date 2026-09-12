<?php

namespace App\Livewire\Admin;

use App\Models\ClassSession;
use App\Models\Cycle;
use App\Services\AttendanceService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Calendar extends Component
{
    /**
     * Día de clase en formato ISO, que es como lo guarda el ciclo.
     *
     * @var array<int, string>
     */
    public const WEEKDAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    #[Url(as: 'ciclo', except: '')]
    public string $cycleId = '';

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public ?string $class_weekday = null;

    public string $newDate = '';

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public function mount(): void
    {
        if ($this->cycleId === '') {
            $this->cycleId = (string) (Cycle::ordered()->value('id') ?? '');
        }

        $this->fillFromCycle();
    }

    public function updatedCycleId(): void
    {
        $this->fillFromCycle();
    }

    private function fillFromCycle(): void
    {
        $cycle = $this->cycle();

        $this->starts_on = $cycle?->starts_on?->toDateString();
        $this->ends_on = $cycle?->ends_on?->toDateString();
        $this->class_weekday = $cycle?->class_weekday === null ? null : (string) $cycle->class_weekday;
    }

    public function save(): void
    {
        $cycle = $this->cycle();

        abort_if($cycle === null, 404);

        $validated = $this->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'class_weekday' => ['required', 'integer', 'between:1,7'],
        ]);

        $cycle->update($validated);

        Flux::toast(variant: 'success', text: __('Calendario guardado.'));
    }

    public function generate(AttendanceService $service): void
    {
        $cycle = $this->cycle();

        abort_if($cycle === null, 404);

        $this->save();

        $created = $service->generateSessions($cycle->refresh());

        Flux::toast(
            variant: $created > 0 ? 'success' : 'warning',
            text: $created > 0
                ? trans_choice('{1}Se agregó un día de clase.|[2,*]Se agregaron :count días de clase.', $created, ['count' => $created])
                : __('No había días nuevos que agregar.'),
        );
    }

    public function addDate(): void
    {
        $cycle = $this->cycle();

        abort_if($cycle === null, 404);

        $this->validate(['newDate' => ['required', 'date']]);

        ClassSession::firstOrCreate(['cycle_id' => $cycle->id, 'date' => $this->newDate]);

        $this->reset('newDate');

        Flux::toast(variant: 'success', text: __('Día agregado.'));
    }

    public function openDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    /**
     * Borrar el día borra la asistencia de ese día: es lo correcto cuando cayó
     * festivo y la clase no existió.
     */
    public function removeSession(): void
    {
        ClassSession::where('cycle_id', (int) $this->cycleId)->findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Día eliminado.'));
    }

    private function cycle(): ?Cycle
    {
        return $this->cycleId === '' ? null : Cycle::find((int) $this->cycleId);
    }

    public function render(): View
    {
        $cycle = $this->cycle();

        $sessions = $cycle === null
            ? collect()
            : $cycle->sessions()->withCount('attendances')->ordered()->get();

        return view('livewire.admin.calendar', [
            'cycles' => Cycle::ordered()->get(),
            'cycle' => $cycle,
            'weekdays' => self::WEEKDAYS,
            'sessions' => $sessions,
            'deleting' => $this->deletingId === null ? null : $sessions->firstWhere('id', $this->deletingId),
        ]);
    }
}
