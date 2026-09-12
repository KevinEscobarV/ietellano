<?php

namespace App\Livewire\Admin;

use App\Models\Cycle;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Cycles extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $code = '';

    public string $level = '';

    public int $semester = 1;

    public int $year = 2026;

    public string $name = '';

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'level', 'semester', 'year', 'name']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cycle = Cycle::findOrFail($id);
        $this->editingId = $cycle->id;
        $this->code = $cycle->code;
        $this->level = $cycle->level;
        $this->semester = $cycle->semester;
        $this->year = $cycle->year;
        $this->name = $cycle->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('cycles', 'code')->ignore($this->editingId)],
            'level' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'integer', 'min:1', 'max:4'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Cycle::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        $this->reset(['editingId', 'code', 'level', 'semester', 'year', 'name']);

        Flux::toast(variant: 'success', text: __('Ciclo guardado.'));
    }

    public function openDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Cycle::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Ciclo eliminado.'));
    }

    public function render(): View
    {
        $cycles = Cycle::query()
            ->withCount(['students', 'groups', 'courses'])
            ->ordered()
            ->paginate(10);

        return view('livewire.admin.cycles', ['cycles' => $cycles]);
    }
}
