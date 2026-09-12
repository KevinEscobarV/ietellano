<?php

namespace App\Livewire;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\BoletinService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Inicio')]
class Dashboard extends Component
{
    /**
     * Semestre que se está mirando, como "2026-2".
     */
    #[Url(as: 'semestre', except: '')]
    public string $term = '';

    public function mount(): void
    {
        // Para un docente sin cargo administrativo, su tablero son sus materias.
        if (auth()->user()->isTeacherOnly()) {
            $this->redirectRoute('teacher.courses', navigate: true);

            return;
        }

        if ($this->term === '') {
            $this->term = $this->terms()->first() ?? '';
        }
    }

    public function render(): View
    {
        $cycles = $this->cycles();
        $courses = $this->courses($cycles);
        $roster = $this->rosterSizes($cycles);
        $graded = $this->gradedCounts($courses);

        return view('livewire.dashboard', [
            'terms' => $this->terms(),
            'enrollment' => $this->enrollment($cycles, $roster),
            'progress' => $this->progress($cycles, $courses, $roster, $graded),
            'behind' => $this->behind($courses, $roster, $graded),
            'alerts' => $this->alerts($cycles, $courses),
            'performance' => $this->performance($courses),
            'atRisk' => $this->atRisk($cycles, $courses),
        ]);
    }

    /**
     * Los semestres que existen en la base, el más reciente primero.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function terms(): \Illuminate\Support\Collection
    {
        return Cycle::query()
            ->select('year', 'semester')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->get()
            ->map(fn (Cycle $cycle) => "{$cycle->year}-{$cycle->semester}");
    }

    /**
     * @return Collection<int, Cycle>
     */
    private function cycles(): Collection
    {
        [$year, $semester] = array_pad(explode('-', $this->term), 2, null);

        return Cycle::query()
            ->where('year', (int) $year)
            ->where('semester', (int) $semester)
            ->ordered()
            ->get();
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     * @return Collection<int, Course>
     */
    private function courses(Collection $cycles): Collection
    {
        return Course::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->with('subject', 'group', 'teacher')
            ->get();
    }

    /**
     * Cuántos estudiantes cubre cada ciclo y cada grupo. Una sola consulta
     * agrupada en vez de una por curso.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @return array{by_cycle: array<int, int>, by_group: array<string, int>, new: array<int, int>}
     */
    private function rosterSizes(Collection $cycles): array
    {
        $byCycle = [];
        $byGroup = [];

        $rows = Enrollment::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->selectRaw('cycle_id, group_id, count(*) as total')
            ->groupBy('cycle_id', 'group_id')
            ->get();

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $byCycle[$row->cycle_id] = ($byCycle[$row->cycle_id] ?? 0) + $total;

            if ($row->group_id !== null) {
                $byGroup["{$row->cycle_id}:{$row->group_id}"] = $total;
            }
        }

        return ['by_cycle' => $byCycle, 'by_group' => $byGroup, 'new' => $this->newStudents($cycles)];
    }

    /**
     * Quién entra por primera vez a la institución: esta matrícula es la única
     * que tiene.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @return array<int, int>
     */
    private function newStudents(Collection $cycles): array
    {
        return Enrollment::query()
            ->whereIn('cycle_id', $cycles->pluck('id'))
            ->whereIn('student_id', Enrollment::query()
                ->selectRaw('student_id')
                ->groupBy('student_id')
                ->havingRaw('count(*) = 1'))
            ->selectRaw('cycle_id, count(*) as total')
            ->groupBy('cycle_id')
            ->pluck('total', 'cycle_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Notas ya puestas por curso.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, int>
     */
    private function gradedCounts(Collection $courses): array
    {
        return Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereNotNull('score')
            ->selectRaw('course_id, count(*) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Cuántos estudiantes le corresponden a un curso: todo el ciclo si no
     * tiene grupo, solo su grupo si lo tiene.
     *
     * @param  array{by_cycle: array<int, int>, by_group: array<string, int>, new: array<int, int>}  $roster
     */
    private function expected(Course $course, array $roster): int
    {
        return $course->group_id !== null
            ? ($roster['by_group']["{$course->cycle_id}:{$course->group_id}"] ?? 0)
            : ($roster['by_cycle'][$course->cycle_id] ?? 0);
    }

    /**
     * @param  Collection<int, Cycle>  $cycles
     * @param  array{by_cycle: array<int, int>, by_group: array<string, int>, new: array<int, int>}  $roster
     * @return array{total: int, cycles: list<array<string, mixed>>}
     */
    private function enrollment(Collection $cycles, array $roster): array
    {
        $lines = $cycles->map(fn (Cycle $cycle) => [
            'label' => $cycle->label,
            'total' => $roster['by_cycle'][$cycle->id] ?? 0,
            'new' => $roster['new'][$cycle->id] ?? 0,
            'groups' => $cycle->groups
                ->map(fn ($group) => [
                    'name' => $group->name,
                    'total' => $roster['by_group']["{$cycle->id}:{$group->id}"] ?? 0,
                ])
                ->filter(fn (array $group) => $group['total'] > 0)
                ->values()
                ->all(),
        ])->values()->all();

        return ['total' => array_sum(array_column($lines, 'total')), 'cycles' => $lines];
    }

    /**
     * Avance de calificación: notas puestas contra las que se esperan.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @param  Collection<int, Course>  $courses
     * @param  array{by_cycle: array<int, int>, by_group: array<string, int>, new: array<int, int>}  $roster
     * @param  array<int, int>  $graded
     * @return array{done: int, expected: int, percent: int, cycles: list<array<string, mixed>>}
     */
    private function progress(Collection $cycles, Collection $courses, array $roster, array $graded): array
    {
        $lines = $cycles->map(function (Cycle $cycle) use ($courses, $roster, $graded) {
            $own = $courses->where('cycle_id', $cycle->id);
            $expected = $own->sum(fn (Course $course) => $this->expected($course, $roster));
            $done = $own->sum(fn (Course $course) => $graded[$course->id] ?? 0);

            return [
                'label' => $cycle->label,
                'done' => $done,
                'expected' => $expected,
                'percent' => $expected > 0 ? (int) round($done / $expected * 100) : 0,
            ];
        })->values()->all();

        $done = array_sum(array_column($lines, 'done'));
        $expected = array_sum(array_column($lines, 'expected'));

        return [
            'done' => $done,
            'expected' => $expected,
            'percent' => $expected > 0 ? (int) round($done / $expected * 100) : 0,
            'cycles' => $lines,
        ];
    }

    /**
     * Los cursos con más notas por poner. Es la lista de a quién hay que
     * apurar.
     *
     * @param  Collection<int, Course>  $courses
     * @param  array{by_cycle: array<int, int>, by_group: array<string, int>, new: array<int, int>}  $roster
     * @param  array<int, int>  $graded
     * @return list<array<string, mixed>>
     */
    private function behind(Collection $courses, array $roster, array $graded): array
    {
        return $courses
            ->map(function (Course $course) use ($roster, $graded) {
                $expected = $this->expected($course, $roster);

                return [
                    'subject' => $course->subject?->name ?? $course->code,
                    'group' => $course->group?->name,
                    'period' => $course->period,
                    'teacher' => $course->teacher?->name,
                    'done' => $graded[$course->id] ?? 0,
                    'expected' => $expected,
                    'missing' => max($expected - ($graded[$course->id] ?? 0), 0),
                ];
            })
            ->filter(fn (array $line) => $line['missing'] > 0)
            ->sortByDesc('missing')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * Lo que está a medias y se puede arreglar desde el panel.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @param  Collection<int, Course>  $courses
     * @return list<array{tone: string, text: string, route: ?string}>
     */
    private function alerts(Collection $cycles, Collection $courses): array
    {
        $withoutTeacher = $courses->whereNull('teacher_id')->count();

        $teachers = Teacher::query()
            ->whereHas('courses', fn ($query) => $query->whereIn('cycle_id', $cycles->pluck('id')))
            ->whereNull('user_id')
            ->count();

        // Los correos de relleno rompen el cruce de la importación de notas.
        $placeholders = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->whereIn('cycle_id', $cycles->pluck('id')))
            ->where('email', 'like', '%@iellano.com')
            ->count();

        return array_values(array_filter([
            $teachers > 0
                ? ['tone' => 'warning', 'text' => trans_choice('{1}Un docente sin cuenta de acceso|[2,*]:count docentes sin cuenta de acceso', $teachers, ['count' => $teachers]), 'route' => 'admin.teachers']
                : ['tone' => 'ok', 'text' => __('Todos los docentes tienen acceso'), 'route' => null],
            $withoutTeacher > 0
                ? ['tone' => 'warning', 'text' => trans_choice('{1}Un curso sin docente asignado|[2,*]:count cursos sin docente asignado', $withoutTeacher, ['count' => $withoutTeacher]), 'route' => 'admin.structure']
                : ['tone' => 'ok', 'text' => __('Todos los cursos tienen docente'), 'route' => null],
            $placeholders > 0
                ? ['tone' => 'warning', 'text' => trans_choice('{1}Un estudiante con correo de relleno|[2,*]:count estudiantes con correo de relleno', $placeholders, ['count' => $placeholders]), 'route' => 'admin.students']
                : null,
        ]));
    }

    /**
     * Cómo se reparten las notas del semestre en la escala del Decreto 1290.
     *
     * @param  Collection<int, Course>  $courses
     * @return array{total: int, average: ?float, levels: list<array{name: string, total: int, percent: int}>}
     */
    private function performance(Collection $courses): array
    {
        $scores = Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereNotNull('score')
            ->pluck('score');

        $buckets = ['Superior' => 0, 'Alto' => 0, 'Básico' => 0, 'Bajo' => 0];

        foreach ($scores as $score) {
            $level = BoletinService::performance((float) $score);

            if ($level !== null) {
                $buckets[$level]++;
            }
        }

        $total = $scores->count();

        return [
            'total' => $total,
            'average' => $total > 0 ? round($scores->sum() / $total, 2) : null,
            'levels' => collect($buckets)
                ->map(fn (int $count, string $name) => [
                    'name' => $name,
                    'total' => $count,
                    'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Estudiantes cuyo promedio del semestre no alcanza para aprobar. Solo
     * cuentan los que ya tienen alguna nota.
     *
     * @param  Collection<int, Cycle>  $cycles
     * @param  Collection<int, Course>  $courses
     * @return array{total: int, students: list<array{name: string, cycle: string, average: float}>}
     */
    private function atRisk(Collection $cycles, Collection $courses): array
    {
        // El corte se aplica en PHP y no con un `having`: al ligar el 3.0 como
        // parámetro, SQLite lo compara como texto contra un número y deja pasar
        // todas las filas. Es una fila por estudiante del semestre, no pesa.
        $rows = Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereNotNull('score')
            ->selectRaw('student_id, avg(score) as average')
            ->groupBy('student_id')
            ->get()
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'average' => round((float) $row->average, 2),
            ])
            ->filter(fn (array $row) => $row['average'] < BoletinService::PASSING_SCORE)
            ->sortBy('average')
            ->values();

        $students = Student::query()
            ->whereIn('id', $rows->pluck('student_id'))
            ->with(['enrollments' => fn ($query) => $query->whereIn('cycle_id', $cycles->pluck('id'))->with('cycle')])
            ->get()
            ->keyBy('id');

        return [
            'total' => $rows->count(),
            'students' => $rows->take(6)->map(function (array $row) use ($students) {
                $student = $students[$row['student_id']] ?? null;

                return [
                    'name' => trim(($student?->last_name ?? '').' '.($student?->first_name ?? '')),
                    'cycle' => $student?->enrollments->first()?->cycle?->label ?? '',
                    'average' => $row['average'],
                ];
            })->values()->all(),
        ];
    }
}
