<?php

namespace App\Livewire\Teacher;

use App\Enums\AttendanceStatus;
use App\Models\Attendance as AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\TermService;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Attendance extends Component
{
    public Course $course;

    #[Url(as: 'sesion', except: '')]
    public string $sessionId = '';

    public string $search = '';

    /**
     * Estado de cada estudiante en la sesión abierta, indexado por su id.
     *
     * @var array<int, string>
     */
    public array $status = [];

    /** @var array<int, int>|null */
    protected ?array $rosterIds = null;

    public function mount(Course $course): void
    {
        $this->authorize('view', $course);

        $this->course = $course->load('subject', 'group', 'cycle');

        if ($this->sessionId === '') {
            $this->sessionId = (string) ($this->currentSession()?->id ?? '');
        }

        $this->status = $this->savedStatus();
    }

    public function updatedSessionId(): void
    {
        $this->status = $this->savedStatus();
    }

    /**
     * Marcar a alguien guarda la planilla entera: así queda registrado que la
     * clase se dictó y quién no llegó. Sin eso, un día sin fallas sería
     * indistinguible de un día en que nadie pasó asistencia.
     */
    public function updatedStatus(mixed $value, ?string $key = null): void
    {
        if ($key === null || $this->session() === null) {
            return;
        }

        $this->authorize('attend', $this->course);

        abort_unless($this->isInRoster((int) $key), 403);

        $this->persist();

        $this->dispatch('attendance-saved');
    }

    public function markAllPresent(): void
    {
        $this->authorize('attend', $this->course);

        abort_if($this->session() === null, 404);

        $this->status = array_map(fn () => AttendanceStatus::Present->value, $this->status);

        $this->persist();

        Flux::toast(variant: 'success', text: __('Todos quedaron como presentes.'));
    }

    /**
     * Si la planilla se puede marcar ahora mismo. Se consulta en cada petición:
     * una ventana puede vencerse con la pantalla abierta.
     */
    #[Computed]
    public function editable(): bool
    {
        return auth()->user()->can('attend', $this->course);
    }

    /**
     * Si alguien ya pasó asistencia este día. Sin eso, la planilla arranca con
     * todos en "Presente" porque es lo que el docente va a marcar casi siempre
     * — pero eso es una propuesta, no un registro, y no puede leerse como tal.
     */
    #[Computed]
    public function recorded(): bool
    {
        $session = $this->session();

        return $session !== null && AttendanceRecord::where('course_id', $this->course->id)
            ->where('class_session_id', $session->id)
            ->exists();
    }

    #[Computed]
    public function openUntil(): ?CarbonInterface
    {
        return app(TermService::class)->openUntil($this->course);
    }

    private function persist(): void
    {
        $session = $this->session();

        if ($session === null) {
            return;
        }

        $now = now();

        $rows = collect($this->rosterIds())
            ->keys()
            ->map(fn (int $studentId) => [
                'class_session_id' => $session->id,
                'course_id' => $this->course->id,
                'student_id' => $studentId,
                'status' => $this->valid($this->status[$studentId] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            AttendanceRecord::upsert($rows, ['course_id', 'class_session_id', 'student_id'], ['status', 'updated_at']);
        }
    }

    private function valid(?string $status): string
    {
        return AttendanceStatus::tryFrom((string) $status)?->value ?? AttendanceStatus::Present->value;
    }

    /**
     * La clase de hoy si hoy hay clase, si no la última que ya pasó. Al
     * empezar el semestre, cuando todas están por delante, la primera.
     */
    private function currentSession(): ?ClassSession
    {
        $sessions = $this->sessions();
        $today = now()->startOfDay();

        return $sessions->firstWhere(fn (ClassSession $s) => $s->date->lte($today))
            ?? $sessions->last();
    }

    private function session(): ?ClassSession
    {
        if ($this->sessionId === '') {
            return null;
        }

        return $this->sessions()->firstWhere('id', (int) $this->sessionId);
    }

    /**
     * @return Collection<int, ClassSession>
     */
    private function sessions(): Collection
    {
        return once(fn () => ClassSession::where('cycle_id', $this->course->cycle_id)->ordered()->get());
    }

    /**
     * @return array<int, string>
     */
    private function savedStatus(): array
    {
        $session = $this->session();

        $saved = $session === null
            ? collect()
            : AttendanceRecord::where('course_id', $this->course->id)
                ->where('class_session_id', $session->id)
                ->pluck('status', 'student_id');

        return collect($this->rosterIds())
            ->keys()
            ->mapWithKeys(fn (int $id) => [
                $id => ($saved[$id] ?? AttendanceStatus::Present)->value,
            ])
            ->all();
    }

    /**
     * La lista blanca de a quién se le puede marcar algo: sin esto el
     * formulario aceptaría cualquier id que llegara desde el navegador.
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
            ->get();
    }

    /**
     * @return array{total: int, present: int, absent: int, excused: int}
     */
    private function summary(): array
    {
        $counts = array_count_values($this->status);

        return [
            'total' => count($this->status),
            'present' => $counts[AttendanceStatus::Present->value] ?? 0,
            'absent' => $counts[AttendanceStatus::Absent->value] ?? 0,
            'excused' => $counts[AttendanceStatus::Excused->value] ?? 0,
        ];
    }

    public function render(AttendanceService $service): View
    {
        return view('livewire.teacher.attendance', [
            'students' => $this->roster(),
            'sessions' => $this->sessions(),
            'summary' => $this->summary(),
            'progress' => $service->courseProgress($this->course),
            'totals' => $service->studentTotals($this->course),
        ])->title(__('Asistencia').' · '.($this->course->subject?->name ?? $this->course->code));
    }
}
