<?php

namespace App\Livewire\Admin;

use App\Models\Cycle;
use App\Models\Student;
use App\Services\CertificateService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Certificados')]
class Certificates extends Component
{
    private const MONTHS = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public ?string $cycleId = '';

    public ?string $studentId = '';

    public bool $bothSemesters = false;

    public function updatedCycleId(): void
    {
        $this->studentId = '';
        $this->bothSemesters = false;
    }

    public function render(CertificateService $service): View
    {
        $cycles = Cycle::whereNotIn('id', Cycle::whereNotNull('previous_cycle_id')->pluck('previous_cycle_id'))
            ->orderBy('level')
            ->get();

        $students = $this->cycleId
            ? Student::query()
                ->whereRelation('enrollments', 'cycle_id', $this->cycleId)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();

        $cycle = $this->cycleId ? $cycles->firstWhere('id', $this->cycleId) : null;
        $certificate = null;

        if ($cycle && $this->studentId) {
            $student = $students->firstWhere('id', $this->studentId);

            if ($student) {
                $certificate = $service->generate($student, $cycle, $this->bothSemesters);
            }
        }

        $now = Carbon::now();

        return view('livewire.admin.certificates', [
            'cycles' => $cycles,
            'students' => $students,
            'certificate' => $certificate,
            'hasPrevious' => (bool) $cycle?->previous_cycle_id,
            'issuedDate' => self::MONTHS[$now->month].' '.$now->day.' de '.$now->year,
        ]);
    }
}
