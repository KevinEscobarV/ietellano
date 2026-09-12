<?php

namespace App\Livewire\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Estructura académica')]
class Structure extends Component
{
    /**
     * @var array<string, string>
     */
    public const TABS = [
        'cycles' => 'Ciclos',
        'groups' => 'Grupos',
        'subjects' => 'Materias',
        'courses' => 'Cursos',
        'areas' => 'Áreas',
        'calendar' => 'Calendario',
    ];

    #[Url]
    public string $tab = 'cycles';

    public function selectTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
        }
    }

    public function render(): View
    {
        return view('livewire.admin.structure', ['tabs' => self::TABS]);
    }
}
