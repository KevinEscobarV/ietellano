<?php

namespace App\Livewire\Consulta;

use App\Models\Cycle;
use App\Models\Student;
use App\Services\BoletinService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * El boletín que ve el propio estudiante. No hay usuario detrás: la sesión
 * solo recuerda a quién verificó la consulta, así que todo lo que se muestra
 * —y lo que se descarga— sale de ese identificador y nunca de la URL.
 */
#[Layout('layouts.consulta')]
#[Title('Mi boletín')]
class Boletin extends Component
{
    public const SESSION_KEY = 'consulta.student_id';

    public ?int $cycleId = null;

    public bool $bothSemesters = false;

    public function mount(): void
    {
        if ($this->student === null) {
            $this->redirectRoute('consulta.lookup');

            return;
        }

        $this->cycleId = $this->cycles->first()?->id;
    }

    /**
     * El estudiante que quedó verificado en la consulta, si la sesión sigue
     * viva.
     */
    #[Computed]
    public function student(): ?Student
    {
        $id = session(self::SESSION_KEY);

        return $id === null ? null : Student::find($id);
    }

    /**
     * Los semestres que puede consultar: aquellos en los que está matriculado,
     * el más reciente primero. El primer semestre de un ciclo de dos también se
     * ofrece suelto: quien se retiró después de 3A no tiene otro boletín.
     *
     * @return Collection<int, Cycle>
     */
    #[Computed]
    public function cycles(): Collection
    {
        return Cycle::query()
            ->whereRelation('enrollments', 'student_id', $this->student->id)
            ->ordered()
            ->get();
    }

    public function selectCycle(int $cycleId): void
    {
        if ($this->cycles->contains('id', $cycleId)) {
            $this->cycleId = $cycleId;
            $this->bothSemesters = false;
        }
    }

    public function salir(): void
    {
        session()->forget(self::SESSION_KEY);

        $this->redirectRoute('consulta.lookup');
    }

    public function render(BoletinService $service): View
    {
        $student = $this->student;

        if ($student === null) {
            $this->redirectRoute('consulta.lookup');
        }

        $cycles = $student === null ? new Collection : $this->cycles;
        $cycle = $cycles->firstWhere('id', $this->cycleId);
        $hasPrevious = (bool) $cycle?->previous_cycle_id;

        $boletin = $student !== null && $cycle !== null
            ? $service->generate($student, $cycle, $this->bothSemesters && $hasPrevious)
            : null;

        return view('livewire.consulta.boletin', [
            'student' => $student,
            'cycles' => $cycles,
            'cycle' => $cycle,
            'boletin' => $boletin,
            'hasPrevious' => $hasPrevious,
            'hasGrades' => $boletin !== null && collect($boletin['lines'])->contains(fn (array $line) => $line['final'] !== null),
            'passingScore' => BoletinService::PASSING_SCORE,
        ]);
    }
}
