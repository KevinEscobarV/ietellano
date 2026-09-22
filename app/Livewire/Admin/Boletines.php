<?php

namespace App\Livewire\Admin;

use App\Models\Cycle;
use App\Models\Student;
use App\Services\BoletinService;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Boletines')]
class Boletines extends Component
{
    public ?string $cycleId = '';

    public ?string $studentId = '';

    public bool $bothSemesters = false;

    public function updatedCycleId(): void
    {
        $this->studentId = '';
        $this->bothSemesters = false;
    }

    public function render(BoletinService $service): View
    {
        $cycles = Cycle::ordered()->get();

        $students = $this->cycleId
            ? Student::query()
                ->whereRelation('enrollments', 'cycle_id', $this->cycleId)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();

        $cycle = $this->cycleId ? $cycles->firstWhere('id', $this->cycleId) : null;
        $boletin = null;

        if ($cycle && $this->studentId) {
            $student = $students->firstWhere('id', $this->studentId);

            if ($student) {
                $boletin = $service->generate($student, $cycle, $this->bothSemesters);
            }
        }

        return view('livewire.admin.boletines', [
            'cycles' => $cycles,
            'students' => $students,
            'boletin' => $boletin,
            'hasPrevious' => (bool) $cycle?->previous_cycle_id,
            'passingScore' => BoletinService::PASSING_SCORE,
        ]);
    }
}
