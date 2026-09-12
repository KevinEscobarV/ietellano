<?php

namespace App\Livewire\Admin;

use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Estudiantes')]
class Students extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $search = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $document = '';

    public string $email = '';

    public string $phone = '';

    public string $username = '';

    public string $city = '';

    /** @var array<int, array{cycle_id: ?int, group_id: ?int}> */
    public array $enrollments = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $studentId): void
    {
        $student = Student::with('enrollments')->findOrFail($studentId);

        $this->editingId = $student->id;
        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->document = (string) $student->document;
        $this->email = $student->email;
        $this->phone = (string) $student->phone;
        $this->username = (string) $student->username;
        $this->city = (string) $student->city;
        $this->enrollments = $student->enrollments
            ->map(fn (Enrollment $e) => ['cycle_id' => $e->cycle_id, 'group_id' => $e->group_id])
            ->all();

        $this->showModal = true;
    }

    public function addEnrollment(): void
    {
        $this->enrollments[] = ['cycle_id' => null, 'group_id' => null];
    }

    public function removeEnrollment(int $index): void
    {
        unset($this->enrollments[$index]);
        $this->enrollments = array_values($this->enrollments);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'enrollments' => ['array'],
            'enrollments.*.cycle_id' => ['nullable', 'exists:cycles,id'],
            'enrollments.*.group_id' => ['nullable', 'exists:groups,id'],
        ]);

        $cycleIds = collect($this->enrollments)->pluck('cycle_id')->filter();

        if ($cycleIds->count() !== $cycleIds->unique()->count()) {
            $this->addError('enrollments', __('No puedes matricular al estudiante dos veces en el mismo ciclo.'));

            return;
        }

        $attributes = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'document' => $validated['document'] ?: null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'username' => $validated['username'] ?: null,
            'city' => $validated['city'] ?: null,
        ];

        if ($this->editingId) {
            $student = Student::findOrFail($this->editingId);
            $student->update($attributes);
        } else {
            $student = Student::create($attributes + ['country' => 'CO', 'lang' => 'es-CO']);
        }

        $this->syncEnrollments($student);

        $this->showModal = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Estudiante guardado.'));
    }

    private function syncEnrollments(Student $student): void
    {
        $keep = [];

        foreach ($this->enrollments as $enrollment) {
            if (empty($enrollment['cycle_id'])) {
                continue;
            }

            Enrollment::updateOrCreate(
                ['student_id' => $student->id, 'cycle_id' => $enrollment['cycle_id']],
                ['group_id' => $enrollment['group_id'] ?: null],
            );

            $keep[] = $enrollment['cycle_id'];
        }

        $student->enrollments()->whereNotIn('cycle_id', $keep ?: [0])->delete();
    }

    public function openDelete(int $studentId): void
    {
        $this->deletingId = $studentId;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Student::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Estudiante eliminado.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'first_name', 'last_name', 'document', 'email', 'phone', 'username', 'city', 'enrollments']);
    }

    public function render(): View
    {
        $students = Student::query()
            ->with('enrollments.cycle', 'enrollments.group')
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('document', 'like', "%{$this->search}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        return view('livewire.admin.students', [
            'students' => $students,
            'cycles' => Cycle::with('groups')->ordered()->get(),
        ]);
    }
}
