<?php

namespace App\Livewire\Admin;

use App\Concerns\BrowsesCyclesByTerm;
use App\Concerns\ShowsTutorials;
use App\Services\BoletinService;
use App\Services\ConsolidadoService;
use App\Services\TermService;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Las definitivas de un ciclo en una tabla, con el resumen arriba para leer
 * de un vistazo cómo le fue. Se puede sacar en cualquier momento: mientras
 * algún módulo siga abierto sale con el sello de provisional.
 */
#[Title('Consolidados')]
class Consolidados extends Component
{
    use BrowsesCyclesByTerm;
    use ShowsTutorials;

    #[Url(as: 'grupo', except: '')]
    public string $groupId = '';

    #[Url(as: 'vista', except: 'definitiva')]
    public string $view = 'definitiva';

    #[Url(as: 'orden', except: 'nombre')]
    public string $sort = 'nombre';

    /**
     * En los ciclos que duran un año (3B, 4B) se entra por el año completo;
     * "semestre" deja ver solo el segundo semestre, con sus módulos. En los
     * demás ciclos no cambia nada.
     */
    #[Url(as: 'alcance', except: 'anio')]
    public string $span = 'anio';

    /**
     * El año solo tiene definitiva: el M1 y el M2 de cada semestre ya van
     * promediados.
     */
    public function updatedSpan(): void
    {
        if ($this->span !== 'semestre') {
            $this->span = 'anio';
            $this->view = 'definitiva';
        }
    }

    protected function resetCycleSelection(): void
    {
        $this->reset(['groupId', 'span']);
    }

    /**
     * @return list<string>
     */
    protected function tutorials(): array
    {
        return ['consolidados'];
    }

    public function render(ConsolidadoService $service, TermService $term): View
    {
        $data = $this->cycleBrowserData($term);
        $cycle = $data['selected'];
        $groups = $cycle ? $cycle->groups()->orderBy('name')->pluck('name', 'id') : collect();

        $consolidado = $cycle ? $service->forCycle(
            $cycle,
            $this->view,
            $this->span !== 'semestre',
            $this->groupId !== '' && $groups->has((int) $this->groupId) ? (int) $this->groupId : null,
            $this->sort,
        ) : null;

        return view('livewire.admin.consolidados', [
            ...$data,
            'groups' => $groups,
            'consolidado' => $consolidado,
            'downloadParams' => $consolidado ? $this->downloadParams($consolidado) : [],
            'passingScore' => BoletinService::PASSING_SCORE,
            'startTour' => $this->shouldStartTutorial('consolidados'),
        ]);
    }

    /**
     * Lo que se ve en pantalla es lo que se descarga.
     *
     * @param  array<string, mixed>  $consolidado
     * @return array<string, string|int>
     */
    private function downloadParams(array $consolidado): array
    {
        return array_filter([
            'vista' => $consolidado['view'] !== 'definitiva' ? $consolidado['view'] : null,
            'grupo' => $consolidado['group']?->id,
            // El año es lo normal en 3B y 4B; solo se avisa cuando se pidió el semestre.
            'alcance' => $consolidado['yearParts'] && ! $consolidado['year'] ? 'semestre' : null,
            'orden' => $consolidado['sort'] !== 'nombre' ? $consolidado['sort'] : null,
        ]);
    }
}
