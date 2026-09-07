<?php

namespace App\Livewire\Admin;

use App\Models\Subject;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Subjects extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $search = '';

    public string $code = '';

    public string $name = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'name']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $subject = Subject::findOrFail($id);
        $this->editingId = $subject->id;
        $this->code = $subject->code;
        $this->name = $subject->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Subject::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        $this->reset(['editingId', 'code', 'name']);

        Flux::toast(variant: 'success', text: __('Materia guardada.'));
    }

    public function openDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Subject::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Materia eliminada.'));
    }

    public function render(): View
    {
        $subjects = Subject::query()
            ->withCount('courses')
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.subjects', ['subjects' => $subjects]);
    }
}
