<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\Cycle;
use App\Models\Subject;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Areas extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $cycle_id = '';

    public string $name = '';

    public string $code = '';

    public int $display_order = 0;

    /** @var array<int, int> */
    public array $subjectIds = [];

    public function openCreate(): void
    {
        $this->reset(['editingId', 'cycle_id', 'name', 'code', 'display_order', 'subjectIds']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $area = Area::with('subjects')->findOrFail($id);
        $this->editingId = $area->id;
        $this->cycle_id = (string) $area->cycle_id;
        $this->name = $area->name;
        $this->code = (string) $area->code;
        $this->display_order = $area->display_order;
        $this->subjectIds = $area->subjects->pluck('id')->all();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'cycle_id' => ['required', 'exists:cycles,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'display_order' => ['required', 'integer', 'min:0'],
            'subjectIds' => ['array'],
            'subjectIds.*' => ['exists:subjects,id'],
        ]);

        $area = Area::updateOrCreate(
            ['id' => $this->editingId],
            [
                'cycle_id' => $validated['cycle_id'],
                'name' => $validated['name'],
                'code' => $validated['code'] ?: null,
                'display_order' => $validated['display_order'],
            ],
        );

        $area->subjects()->sync(array_map('intval', $this->subjectIds));

        $this->showModal = false;
        $this->reset(['editingId', 'cycle_id', 'name', 'code', 'display_order', 'subjectIds']);

        Flux::toast(variant: 'success', text: __('Área guardada.'));
    }

    public function openDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Area::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Área eliminada.'));
    }

    public function render(): View
    {
        $areas = Area::query()
            ->with('cycle', 'subjects')
            ->orderBy('cycle_id')
            ->orderBy('display_order')
            ->paginate(10);

        $cycleSubjects = $this->cycle_id
            ? Subject::whereHas('courses', fn ($query) => $query->where('cycle_id', $this->cycle_id))->orderBy('name')->get()
            : collect();

        return view('livewire.admin.areas', [
            'areas' => $areas,
            'cycles' => Cycle::ordered()->get(),
            'cycleSubjects' => $cycleSubjects,
        ]);
    }
}
