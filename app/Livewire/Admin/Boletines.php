<?php

namespace App\Livewire\Admin;

use App\Concerns\BrowsesStudentsByTerm;
use App\Concerns\ShowsTutorials;
use App\Services\BoletinService;
use App\Services\TermService;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Los boletines se buscan como las notas: semestre, ciclo y estudiante. La
 * lista dice de una vez a quién le faltan notas, así se sabe si el boletín
 * sale completo antes de abrirlo.
 */
#[Title('Boletines')]
class Boletines extends Component
{
    use BrowsesStudentsByTerm;
    use ShowsTutorials;

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
        return ['boletines'];
    }

    public function render(BoletinService $service, TermService $term): View
    {
        $data = $this->browserData($term);
        $cycle = $data['selected'];
        $hasPrevious = (bool) $cycle?->previous_cycle_id;

        return view('livewire.admin.boletines', [
            ...$data,
            'boletin' => $data['student'] ? $service->generate($data['student'], $cycle, $this->bothSemesters && $hasPrevious) : null,
            'hasPrevious' => $hasPrevious,
            'passingScore' => BoletinService::PASSING_SCORE,
            'startTour' => $this->shouldStartTutorial('boletines'),
        ]);
    }
}
