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

    /**
     * Qué notas lleva el boletín: un módulo, el semestre o, en 3B y 4B, el
     * año completo (BoletinService::SPANS).
     */
    #[Url(as: 'notas', except: 'semestre')]
    public string $span = 'semestre';

    protected function resetStudent(): void
    {
        $this->reset(['studentId', 'groupId', 'search', 'span']);
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
        $span = $cycle ? BoletinService::span($this->span, $cycle) : 'semestre';

        return view('livewire.admin.boletines', [
            ...$data,
            'boletin' => $data['student'] ? $service->forSpan($data['student'], $cycle, $span) : null,
            'spans' => $cycle ? BoletinService::spans($cycle) : [],
            'currentSpan' => $span,
            'downloadParams' => $span !== 'semestre' ? ['notas' => $span] : [],
            'passingScore' => BoletinService::PASSING_SCORE,
            'startTour' => $this->shouldStartTutorial('boletines'),
        ]);
    }
}
