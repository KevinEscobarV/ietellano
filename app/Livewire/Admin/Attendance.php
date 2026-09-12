<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AttendanceService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Asistencia')]
class Attendance extends Component
{
    #[Url(as: 'ciclo', except: '')]
    public string $cycleId = '';

    #[Url(as: 'grupo', except: '')]
    public string $groupId = '';

    public string $search = '';

    public bool $onlyAtRisk = false;

    public function mount(): void
    {
        if ($this->cycleId === '') {
            $this->cycleId = (string) (Cycle::ordered()->value('id') ?? '');
        }
    }

    public function updatedCycleId(): void
    {
        $this->reset('groupId');
    }

    public function export(AttendanceService $service): StreamedResponse
    {
        $cycle = $this->cycle();
        $rows = $this->rows($service);
        $subjects = $this->subjects();

        $name = 'asistencia-'.str($cycle?->label ?? 'ciclo')->slug().'.csv';

        return response()->streamDownload(function () use ($rows, $subjects) {
            $out = fopen('php://output', 'w');

            // BOM para que Excel abra las tildes bien.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Documento'),
                __('Apellidos'),
                __('Nombres'),
                __('Grupo'),
                ...$subjects->pluck('name')->all(),
                __('Total fallas'),
            ], ';');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['document'],
                    $row['last_name'],
                    $row['first_name'],
                    $row['group'],
                    ...$subjects->map(fn ($subject) => $row['subjects'][$subject->id]['absences'] ?? 0)->all(),
                    $row['total'],
                ], ';');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cycle(): ?Cycle
    {
        return $this->cycleId === '' ? null : Cycle::find((int) $this->cycleId);
    }

    /**
     * @return Collection<int, Course>
     */
    private function courses(): Collection
    {
        if ($this->cycleId === '') {
            return collect();
        }

        return once(fn () => Course::where('cycle_id', (int) $this->cycleId)->with('subject')->get());
    }

    /**
     * Las columnas son las materias, no los cursos: un ciclo con dos grupos
     * tiene dos cursos por materia y cada estudiante solo pertenece a uno, así
     * que separarlos dejaría media tabla en cero.
     *
     * @return Collection<int, Subject>
     */
    private function subjects(): Collection
    {
        return $this->courses()
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(AttendanceService $service): Collection
    {
        if ($this->cycleId === '') {
            return collect();
        }

        $needle = trim($this->search);

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('cycle_id', (int) $this->cycleId)
                ->when($this->groupId !== '', fn ($q) => $q->where('group_id', (int) $this->groupId)))
            ->when($needle !== '', fn ($query) => $query->where(function ($q) use ($needle) {
                $q->where('first_name', 'like', "%{$needle}%")
                    ->orWhere('last_name', 'like', "%{$needle}%")
                    ->orWhere('document', 'like', "%{$needle}%");
            }))
            ->with(['enrollments' => fn ($query) => $query->where('cycle_id', (int) $this->cycleId)->with('group')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $matrix = $service->matrix($students->pluck('id')->all(), $this->courses());

        return $students
            ->map(function (Student $student) use ($matrix) {
                $bySubject = $matrix[$student->id] ?? [];

                return [
                    'id' => $student->id,
                    'document' => $student->document,
                    'last_name' => $student->last_name,
                    'first_name' => $student->first_name,
                    'group' => $student->enrollments->first()?->group?->name ?? '',
                    'subjects' => $bySubject,
                    'total' => array_sum(array_column($bySubject, 'absences')),
                    'lost' => count(array_filter($bySubject, fn (array $line) => $line['lost'])),
                ];
            })
            ->when($this->onlyAtRisk, fn (Collection $rows) => $rows->filter(fn (array $row) => $row['lost'] > 0))
            ->values();
    }

    public function render(AttendanceService $service): View
    {
        return view('livewire.admin.attendance', [
            'cycles' => Cycle::ordered()->get(),
            'groups' => $this->cycleId === '' ? collect() : Group::where('cycle_id', (int) $this->cycleId)->orderBy('name')->get(),
            'subjects' => $this->subjects(),
            'rows' => $this->rows($service),
            'maxRate' => AttendanceService::maxAbsenceRate(),
        ]);
    }
}
