<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Grade;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Editar notas')]
class GradeEditor extends Component
{
    public string $cycleId = '';

    public string $courseId = '';

    /** @var array<int, string|null> */
    public array $grades = [];

    public function updatedCycleId(): void
    {
        $this->courseId = '';
        $this->grades = [];
    }

    public function updatedCourseId(): void
    {
        $this->loadGrades();
    }

    private function loadGrades(): void
    {
        $course = $this->courseId ? Course::find($this->courseId) : null;

        if ($course === null) {
            $this->grades = [];

            return;
        }

        $existing = Grade::where('course_id', $course->id)->pluck('score', 'student_id');

        $this->grades = $this->studentsForCourse($course)
            ->mapWithKeys(fn (Student $student) => [$student->id => $existing[$student->id] ?? null])
            ->all();
    }

    private function studentsForCourse(Course $course): Collection
    {
        return Student::query()
            ->whereHas('enrollments', function ($query) use ($course) {
                $query->where('cycle_id', $course->cycle_id)
                    ->when($course->group_id, fn ($q) => $q->where('group_id', $course->group_id));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function save(): void
    {
        $this->validate([
            'grades' => ['array'],
            'grades.*' => ['nullable', 'numeric', 'between:0,5'],
        ]);

        if ($this->courseId === '') {
            return;
        }

        foreach ($this->grades as $studentId => $score) {
            Grade::updateOrCreate(
                ['course_id' => $this->courseId, 'student_id' => $studentId],
                ['score' => ($score === '' || $score === null) ? null : round((float) $score, 2)],
            );
        }

        Flux::toast(variant: 'success', text: __('Notas guardadas.'));
    }

    public function render(): View
    {
        $cycles = Cycle::orderBy('level')->get();

        $courses = $this->cycleId
            ? Course::where('cycle_id', $this->cycleId)->with('subject', 'group')->orderBy('code')->get()
            : collect();

        $course = $this->courseId ? Course::with('subject', 'group', 'teacher', 'cycle')->find($this->courseId) : null;
        $students = $course ? $this->studentsForCourse($course) : collect();

        return view('livewire.admin.grade-editor', [
            'cycles' => $cycles,
            'courses' => $courses,
            'course' => $course,
            'students' => $students,
        ]);
    }
}
