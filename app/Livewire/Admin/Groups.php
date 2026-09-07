<?php

namespace App\Livewire\Admin;

use App\Models\Cycle;
use App\Models\Group;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Groups extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $cycle_id = '';

    public string $name = '';

    public string $code = '';

    public function openCreate(): void
    {
        $this->reset(['editingId', 'cycle_id', 'name', 'code']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $group = Group::findOrFail($id);
        $this->editingId = $group->id;
        $this->cycle_id = (string) $group->cycle_id;
        $this->name = $group->name;
        $this->code = (string) $group->code;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'cycle_id' => ['required', 'exists:cycles,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
        ]);

        Group::updateOrCreate(
            ['id' => $this->editingId],
            ['cycle_id' => $validated['cycle_id'], 'name' => $validated['name'], 'code' => $validated['code'] ?: null],
        );

        $this->showModal = false;
        $this->reset(['editingId', 'cycle_id', 'name', 'code']);

        Flux::toast(variant: 'success', text: __('Grupo guardado.'));
    }

    public function openDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Group::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Grupo eliminado.'));
    }

    public function render(): View
    {
        $groups = Group::query()
            ->with('cycle')
            ->withCount('enrollments')
            ->orderBy('cycle_id')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.groups', [
            'groups' => $groups,
            'cycles' => Cycle::orderBy('level')->get(),
        ]);
    }
}
