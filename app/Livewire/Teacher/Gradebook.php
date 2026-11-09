<?php

namespace App\Livewire\Teacher;

use App\Concerns\ShowsTutorials;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Services\BoletinService;
use App\Services\TermService;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La planilla de notas de un curso. Es la misma para el docente y para la
 * coordinación (Admin\CourseGrades la extiende): lo único que cambia es quién
 * entra, quién escribe y a dónde lleva el botón de volver.
 */
class Gradebook extends Component
{
    use ShowsTutorials;

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
        $this->authorizeAccess($course);

        $this->course = $course->load('subject', 'group', 'cycle', 'teacher');
        $this->scores = $this->savedScores();
    }

    /**
     * Si la planilla se puede escribir ahora mismo. Se consulta en cada
     * petición y no se guarda en el componente: una ventana puede vencerse con
     * la pantalla abierta.
     */
    #[Computed]
    public function editable(): bool
    {
        return $this->canWrite();
    }

    protected function authorizeAccess(Course $course): void
    {
        $this->authorize('view', $course);
    }

    protected function canWrite(): bool
    {
        return auth()->user()->can('grade', $this->course);
    }

    protected function authorizeWrite(): void
    {
        $this->authorize('grade', $this->course);
    }

    /**
     * @return array{url: string, label: string}
     */
    protected function backLink(): array
    {
        return ['url' => route('teacher.courses'), 'label' => __('Mis materias')];
    }

    /**
     * Si quien mira la planilla es la coordinación y no el docente del curso.
     */
    protected function managedByCoordination(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    protected function tutorials(): array
    {
        return ['planilla'];
    }

    /**
     * Hasta cuándo dura el permiso de un semestre cerrado, para avisarlo.
     */
    #[Computed]
    public function openUntil(): ?CarbonInterface
    {
        return app(TermService::class)->openUntil($this->course);
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

        $this->authorizeWrite();

        $studentId = (int) $key;

        abort_unless($this->isInRoster($studentId), 403);

        $this->scores[$key] = $value = $this->cleanInput($value);

        $this->validateOnly("scores.{$key}");

        $this->persist($studentId, $value);

        $this->dispatch('grade-saved');
    }

    public function saveAll(): void
    {
        $this->authorizeWrite();

        $this->scores = array_map(fn (mixed $score) => $this->cleanInput($score), $this->scores);

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
     * En Colombia la nota se escribe con coma (3,5). La casilla es de texto
     * para que el navegador no la descarte en silencio, y aquí se pasa a punto
     * antes de validarla.
     */
    private function cleanInput(mixed $score): mixed
    {
        return is_string($score) ? str_replace(',', '.', trim($score)) : $score;
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
            array_map(fn ($score) => is_numeric($score) ? (float) $score : null, $this->scores),
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
            'back' => $this->backLink(),
            'byCoordination' => $this->managedByCoordination(),
            'cycleClosed' => app(TermService::class)->isClosed($this->course->cycle_id),
            'startTour' => $this->shouldStartTutorial('planilla'),
        ])->title($this->pageTitle());
    }
}
