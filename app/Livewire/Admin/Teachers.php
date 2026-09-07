<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Teacher;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Docentes')]
class Teachers extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $search = '';

    public string $username = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    /** @var array<int, int> */
    public array $assignedCourseIds = [];

    public string $filterCycleId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $teacherId): void
    {
        $teacher = Teacher::with('courses')->findOrFail($teacherId);

        $this->editingId = $teacher->id;
        $this->username = (string) $teacher->username;
        $this->first_name = $teacher->first_name;
        $this->last_name = $teacher->last_name;
        $this->email = (string) $teacher->email;
        $this->phone = (string) $teacher->phone;
        $this->assignedCourseIds = $teacher->courses->pluck('id')->all();
        $this->filterCycleId = '';

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('teachers', 'username')->ignore($this->editingId)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'assignedCourseIds' => ['array'],
            'assignedCourseIds.*' => ['exists:courses,id'],
        ]);

        $attributes = [
            'username' => $validated['username'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
        ];

        if ($this->editingId) {
            $teacher = Teacher::findOrFail($this->editingId);
            $teacher->update($attributes);
        } else {
            $teacher = Teacher::create($attributes);
        }

        $this->syncCourses($teacher);

        $this->showModal = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Docente guardado.'));
    }

    private function syncCourses(Teacher $teacher): void
    {
        $ids = array_map('intval', $this->assignedCourseIds);

        Course::where('teacher_id', $teacher->id)->whereNotIn('id', $ids ?: [0])->update(['teacher_id' => null]);

        if ($ids !== []) {
            Course::whereIn('id', $ids)->update(['teacher_id' => $teacher->id]);
        }
    }

    public function openDelete(int $teacherId): void
    {
        $this->deletingId = $teacherId;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Teacher::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Docente eliminado.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'username', 'first_name', 'last_name', 'email', 'phone', 'assignedCourseIds', 'filterCycleId']);
    }

    public function render(): View
    {
        $teachers = Teacher::query()
            ->withCount('courses')
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        $filterCourses = $this->filterCycleId
            ? Course::where('cycle_id', $this->filterCycleId)->with('subject', 'group', 'teacher')->orderBy('code')->get()
            : collect();

        return view('livewire.admin.teachers', [
            'teachers' => $teachers,
            'cycles' => Cycle::orderBy('level')->get(),
            'filterCourses' => $filterCourses,
            'assignedCourses' => Course::whereIn('id', $this->assignedCourseIds)->with('subject', 'cycle')->orderBy('code')->get(),
        ]);
    }
}
