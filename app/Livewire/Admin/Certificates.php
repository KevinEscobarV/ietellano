<?php

namespace App\Livewire\Admin;

use App\Concerns\BrowsesStudentsByTerm;
use App\Concerns\ShowsTutorials;
use App\Services\CertificateService;
use App\Services\TermService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Los certificados se buscan como las notas: semestre, ciclo y estudiante.
 */
#[Title('Certificados')]
class Certificates extends Component
{
    use BrowsesStudentsByTerm;
    use ShowsTutorials;

    private const MONTHS = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    #[Url(as: 'dos', except: false)]
    public bool $bothSemesters = false;

    protected function resetStudent(): void
    {
        $this->reset(['studentId', 'groupId', 'search', 'bothSemesters']);
    }

    /**
     * @return list<string>
     */
    protected function tutorials(): array
    {
        return ['certificados'];
    }

    public function render(CertificateService $service, TermService $term): View
    {
        $data = $this->browserData($term);
        $cycle = $data['selected'];
        $canCombine = $cycle !== null && $service->canCombine($cycle);
        $combining = $this->bothSemesters && $canCombine;

        $now = Carbon::now();

        return view('livewire.admin.certificates', [
            ...$data,
            'certificate' => $data['student'] ? $service->generate($data['student'], $cycle, $combining) : null,
            'hasPrevious' => (bool) $cycle?->previous_cycle_id,
            'canCombine' => $canCombine,
            'combining' => $combining,
            'issuedDate' => self::MONTHS[$now->month].' '.$now->day.' de '.$now->year,
            'startTour' => $this->shouldStartTutorial('certificados'),
        ]);
    }
}
