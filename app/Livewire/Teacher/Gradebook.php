<?php

namespace App\Livewire\Teacher;

use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Services\BoletinService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class Gradebook extends Component
{
    public Course $course;

    public string $search = '';

    public bool $onlyPending = false;

    /** @var array<int, int>|null */
    protected ?array $rosterIds = null;

    /**
     * Nota de cada estudiante, indexada por su id. Es el espejo de lo que hay
     * guardado: cada campo se persiste al salir de él.
     *
     * @var array<int, string|null>
     */
    public array $scores = [];

    public function mount(Course $course): void
    {
        $this->authorize('grade', $course);

        $this->course = $course->load('subject', 'group', 'cycle');
        $this->scores = $this->savedScores();
    }

    private function pageTitle(): string
    {
        return __('Notas').' · '.($this->course->subject?->name ?? $this->course->code);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'scores.*' => ['nullable', 'numeric', 'between:0,5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'scores.*.numeric' => __('La nota debe ser un número.'),
            'scores.*.between' => __('La nota va de 0 a 5.'),
        ];
    }

    /**
     * Guardado automático: en cuanto el docente sale de una casilla, esa nota
     * queda escrita. Así nadie pierde una planilla por cerrar la pestaña.
     */
    public function updatedScores(mixed $value, ?string $key = null): void
    {
        // Livewire también avisa cuando se reemplaza el arreglo entero; ahí no
        // hay una casilla concreta que guardar. Eso lo cubre saveAll().
        if ($key === null) {
            return;
        }

        $studentId = (int) $key;

        abort_unless($this->isInRoster($studentId), 403);

        $this->validateOnly("scores.{$key}");

        $this->persist($studentId, $value);

        $this->dispatch('grade-saved');
    }

    public function saveAll(): void
    {
        $this->validate();

        $roster = $this->rosterIds();

        foreach ($this->scores as $studentId => $score) {
            if (isset($roster[(int) $studentId])) {
                $this->persist((int) $studentId, $score);
            }
        }

        Flux::toast(variant: 'success', text: __('Planilla guardada.'));
    }

    private function persist(int $studentId, mixed $score): void
    {
        Grade::updateOrCreate(
            ['course_id' => $this->course->id, 'student_id' => $studentId],
            ['score' => $this->normalize($score)],
        );
    }

    private function normalize(mixed $score): ?float
    {
        return ($score === null || $score === '') ? null : round((float) $score, 2);
    }

    /**
     * Los ids del curso, resueltos una sola vez por petición. Son la lista
     * blanca de a quién se le puede escribir una nota: sin esto, el formulario
     * aceptaría cualquier id que llegara desde el navegador.
     *
     * @return array<int, int>
     */
    private function rosterIds(): array
    {
        return $this->rosterIds ??= array_flip($this->course->rosterQuery()->pluck('id')->all());
    }

    private function isInRoster(int $studentId): bool
    {
        return isset($this->rosterIds()[$studentId]);
    }

    /**
     * @return array<int, string|null>
     */
    private function savedScores(): array
    {
        $saved = Grade::where('course_id', $this->course->id)->pluck('score', 'student_id');

        return $this->course->rosterQuery()
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => $saved[$id] ?? null])
            ->all();
    }

    /**
     * @return Collection<int, Student>
     */
    private function roster(): Collection
    {
        $needle = trim($this->search);

        return $this->course->rosterQuery()
            ->when($needle !== '', fn ($query) => $query->where(function ($q) use ($needle) {
                $q->where('first_name', 'like', "%{$needle}%")
                    ->orWhere('last_name', 'like', "%{$needle}%")
                    ->orWhere('document', 'like', "%{$needle}%");
            }))
            ->get()
            ->when($this->onlyPending, fn (Collection $students) => $students->filter(
                fn (Student $student) => in_array($this->scores[$student->id] ?? null, [null, ''], true),
            ))
            ->values();
    }

    /**
     * Resumen del curso. Se calcula sobre la planilla completa, no sobre lo que
     * el buscador deje a la vista.
     *
     * @return array{total: int, graded: int, pending: int, passing: int, failing: int, average: ?float, percent: int}
     */
    private function summary(): array
    {
        $entered = array_values(array_filter(
            array_map(fn ($score) => ($score === null || $score === '') ? null : (float) $score, $this->scores),
            fn (?float $score) => $score !== null,
        ));

        $total = count($this->scores);
        $graded = count($entered);
        $passing = count(array_filter($entered, fn (float $s) => $s >= BoletinService::PASSING_SCORE));

        return [
            'total' => $total,
            'graded' => $graded,
            'pending' => $total - $graded,
            'passing' => $passing,
            'failing' => $graded - $passing,
            'average' => $graded > 0 ? round(array_sum($entered) / $graded, 2) : null,
            'percent' => $total > 0 ? (int) round($graded / $total * 100) : 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.teacher.gradebook', [
            'students' => $this->roster(),
            'summary' => $this->summary(),
        ])->title($this->pageTitle());
    }
}
