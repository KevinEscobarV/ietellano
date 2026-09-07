<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Subject;
use App\Models\Teacher;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Courses extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $search = '';

    public string $code = '';

    public string $cycle_id = '';

    public string $subject_id = '';

    public ?int $group_id = null;

    public ?int $teacher_id = null;

    public int $period = 1;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'cycle_id', 'subject_id', 'group_id', 'teacher_id', 'period']);
        $this->period = 1;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $course = Course::findOrFail($id);
        $this->editingId = $course->id;
        $this->code = $course->code;
        $this->cycle_id = (string) $course->cycle_id;
        $this->subject_id = (string) $course->subject_id;
        $this->group_id = $course->group_id;
        $this->teacher_id = $course->teacher_id;
        $this->period = $course->period;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('courses', 'code')->ignore($this->editingId)],
            'cycle_id' => ['required', 'exists:cycles,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'period' => ['required', 'integer', 'in:1,2'],
        ]);

        Course::updateOrCreate(
            ['id' => $this->editingId],
            [
                'code' => $validated['code'],
                'cycle_id' => $validated['cycle_id'],
                'subject_id' => $validated['subject_id'],
                'group_id' => $validated['group_id'] ?: null,
                'teacher_id' => $validated['teacher_id'] ?: null,
                'period' => $validated['period'],
            ],
        );

        $this->showModal = false;
        $this->reset(['editingId', 'code', 'cycle_id', 'subject_id', 'group_id', 'teacher_id', 'period']);

        Flux::toast(variant: 'success', text: __('Curso guardado.'));
    }

    public function openDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Course::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Curso eliminado.'));
    }

    public function render(): View
    {
        $courses = Course::query()
            ->with('cycle', 'subject', 'group', 'teacher')
            ->when($this->search, fn ($query) => $query->where('code', 'like', "%{$this->search}%"))
            ->orderBy('code')
            ->paginate(15);

        $selectedCycle = $this->cycle_id ? Cycle::with('groups')->find($this->cycle_id) : null;

        return view('livewire.admin.courses', [
            'courses' => $courses,
            'cycles' => Cycle::orderBy('level')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => Teacher::orderBy('last_name')->get(),
            'cycleGroups' => $selectedCycle?->groups ?? collect(),
        ]);
    }
}
