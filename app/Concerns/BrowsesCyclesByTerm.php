<?php

namespace App\Concerns;

use App\Models\Cycle;
use App\Services\TermService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Para las pantallas que se recorren año → semestre → ciclo, como Boletines,
 * Certificados y Consolidados. Todo va en la URL: un enlace o un recargar
 * vuelven al mismo ciclo.
 */
trait BrowsesCyclesByTerm
{
    #[Url(as: 'anio', except: '')]
    public string $year = '';

    #[Url(as: 'semestre', except: '')]
    public string $semester = '';

    #[Url(as: 'ciclo', except: '')]
    public string $cycleId = '';

    /**
     * Livewire lo llama al montar el componente que usa el trait.
     */
    public function mountBrowsesCyclesByTerm(): void
    {
        // Un enlace que ya trae el ciclo manda sobre el año y el semestre.
        $cycle = $this->cycleId !== '' ? Cycle::find((int) $this->cycleId) : null;

        if ($cycle !== null) {
            $this->year = (string) $cycle->year;
            $this->semester = (string) $cycle->semester;

            return;
        }

        $terms = $this->terms();

        if (! $terms->has((int) $this->year)) {
            $this->year = (string) ($terms->keys()->first() ?? '');
        }

        $this->pickSemester();
    }

    public function updatedYear(): void
    {
        $this->pickSemester();
    }

    public function updatedSemester(): void
    {
        $this->pickCycle();
    }

    /**
     * El año y el semestre se alinean con el ciclo, por si llegó de otro lado.
     */
    public function updatedCycleId(): void
    {
        $cycle = Cycle::find((int) $this->cycleId);

        if ($cycle !== null) {
            $this->year = (string) $cycle->year;
            $this->semester = (string) $cycle->semester;
        }

        $this->resetCycleSelection();
    }

    /**
     * Lo que se borra al cambiar de ciclo, porque era del otro. Cada pantalla
     * dice qué es.
     */
    protected function resetCycleSelection(): void {}

    private function pickSemester(): void
    {
        $semesters = $this->terms()->get((int) $this->year, collect());

        if (! $semesters->contains((int) $this->semester)) {
            $this->semester = (string) ($semesters->first() ?? '');
        }

        $this->pickCycle();
    }

    /**
     * Se entra con el primer ciclo elegido, para que la pantalla muestre algo
     * de una vez.
     */
    private function pickCycle(): void
    {
        $this->cycleId = (string) ($this->termCycles()->first()?->id ?? '');
        $this->resetCycleSelection();
    }

    /**
     * Año => semestres que tienen ciclos, lo más reciente primero.
     *
     * @return Collection<int, Collection<int, int>>
     */
    private function terms(): Collection
    {
        return Cycle::query()
            ->select('year', 'semester')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->get()
            ->groupBy('year')
            ->map(fn (Collection $rows) => $rows->pluck('semester')->map(fn ($semester) => (int) $semester)->values());
    }

    /**
     * @return Collection<int, Cycle>
     */
    private function termCycles(): Collection
    {
        if ($this->year === '' || $this->semester === '') {
            return collect();
        }

        return Cycle::query()
            ->where('year', (int) $this->year)
            ->where('semester', (int) $this->semester)
            ->ordered()
            ->withCount('enrollments')
            ->get();
    }

    /**
     * Lo que pintan los pasos 1 y 2 (<x-term-cycle-steps>).
     *
     * @return array{years: Collection<int, int>, semesters: Collection<int, int>, cycleCards: Collection<int, array<string, mixed>>, selected: ?Cycle}
     */
    private function cycleBrowserData(TermService $term): array
    {
        $terms = $this->terms();
        $cycles = $this->termCycles();

        return [
            'years' => $terms->keys(),
            'semesters' => $terms->get((int) $this->year, collect()),
            'cycleCards' => $cycles->map(fn (Cycle $cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'students' => $cycle->enrollments_count,
                'closed' => $term->isClosed($cycle),
                'modules' => collect(Cycle::MODULES)->mapWithKeys(fn (int $period) => [$period => $term->moduleStatus($cycle, $period)]),
            ]),
            'selected' => $cycles->firstWhere('id', (int) $this->cycleId),
        ];
    }
}
