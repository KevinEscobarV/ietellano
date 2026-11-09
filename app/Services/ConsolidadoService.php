<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * El consolidado de un ciclo: las notas de todos sus estudiantes en una sola
 * tabla, con un resumen para leer de un vistazo cómo le fue al ciclo. Cada
 * fila sale del boletín del estudiante, así que las cuentas son las mismas:
 * la definitiva es el promedio de los dos módulos y las materias de un área
 * (Física y Química en los ciclos 5 y 6) van juntas.
 */
class ConsolidadoService
{
    /**
     * Qué nota se lee: la definitiva o la de un solo módulo.
     */
    public const VIEWS = ['definitiva', 'm1', 'm2'];

    public const SORTS = ['nombre', 'puesto'];

    public function __construct(
        private BoletinService $boletines,
        private TermService $term,
        private AttendanceService $attendance,
    ) {}

    /**
     * Con $bothSemesters y un ciclo que tiene semestre anterior, la nota es la
     * definitiva de los dos semestres y entran solo los que cursaron ambos,
     * como en los certificados.
     *
     * @return array{cycle: Cycle, group: ?Group, view: string, both: bool, sort: string, title: string, scope: string, status: array{definitive: bool, label: string, detail: string}, detailLabels: list<string>, showGroup: bool, columns: list<array{name: string, components: list<string>}>, rows: list<array<string, mixed>>, stats: array<string, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function forCycle(Cycle $cycle, string $view = 'definitiva', bool $bothSemesters = false, ?int $groupId = null, string $sort = 'nombre'): array
    {
        $both = $bothSemesters && $cycle->previous_cycle_id !== null;
        $view = ! $both && in_array($view, self::VIEWS, true) ? $view : 'definitiva';
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'nombre';
        $group = $groupId !== null ? $cycle->groups()->find($groupId) : null;

        $columns = [];
        $rows = [];

        foreach ($this->students($cycle, $group, $both) as $student) {
            $boletin = $this->boletines->generate($student, $cycle, $both);
            $cells = [];

            foreach ($boletin['lines'] as $line) {
                $columns[$line['name']] ??= [
                    'name' => $line['name'],
                    'components' => array_column($line['components'], 'name'),
                ];

                $cells[$line['name']] = $this->cell($line, $view, $both);
            }

            $average = BoletinService::average(array_column($cells, 'score'));

            $rows[] = [
                'id' => $student->id,
                'name' => trim("{$student->last_name} {$student->first_name}"),
                'document' => $student->document,
                'group' => $boletin['group'],
                'cells' => $cells,
                'average' => $average,
                'performance' => BoletinService::performance($average),
                'failed' => count(array_filter($cells, fn (array $cell) => $cell['failed'])),
                'pending' => count(array_filter($cells, fn (array $cell) => ! $cell['complete'])),
                'rank' => null,
            ];
        }

        // Mismo orden de materias que el boletín.
        $columns = array_values($columns);
        usort($columns, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        $rows = $this->rank($rows);

        if ($sort === 'puesto') {
            usort($rows, fn (array $a, array $b) => [$a['rank'] === null, $a['rank'], $a['name']] <=> [$b['rank'] === null, $b['rank'], $b['name']]);
        }

        $stats = $this->columnStats($columns, $rows);

        return [
            'cycle' => $cycle,
            'group' => $group,
            'view' => $view,
            'both' => $both,
            'sort' => $sort,
            'title' => $this->title($view, $both),
            'scope' => $this->scope($cycle, $group, $both),
            'status' => $this->status($cycle, $view),
            'detailLabels' => $both ? [__('Sem. 1'), __('Sem. 2'), __('Final')] : [__('M1'), __('M2'), __('Def.')],
            'showGroup' => $group === null && count(array_unique(array_filter(array_column($rows, 'group')))) > 1,
            'columns' => $columns,
            'rows' => $rows,
            'stats' => $stats,
            'summary' => $this->summary($stats, $rows),
        ];
    }

    /**
     * Un consolidado por cada ciclo del semestre que tenga estudiantes.
     *
     * @return list<array<string, mixed>>
     */
    public function forTerm(int $year, int $semester, string $view = 'definitiva'): array
    {
        return Cycle::query()
            ->where('year', $year)
            ->where('semester', $semester)
            ->whereHas('enrollments')
            ->ordered()
            ->get()
            ->map(fn (Cycle $cycle) => $this->forCycle($cycle, $view))
            ->all();
    }

    /**
     * Lo de un docente en un ciclo: una sección por cada materia y grupo que
     * dicta, con la nota de cada módulo, la definitiva y las fallas de cada
     * estudiante. Las materias de los demás docentes no aparecen.
     *
     * @return array{teacher: Teacher, cycle: Cycle, scope: string, status: array{definitive: bool, label: string, detail: string}, sections: list<array<string, mixed>>}
     */
    public function forTeacher(Teacher $teacher, Cycle $cycle): array
    {
        $own = Course::query()
            ->where('cycle_id', $cycle->id)
            ->where('teacher_id', $teacher->id)
            ->get(['subject_id', 'group_id']);

        // Los dos módulos de cada materia y grupo suyos, para sacar la
        // definitiva aunque uno de ellos lo tenga otro docente.
        $courses = Course::query()
            ->where('cycle_id', $cycle->id)
            ->whereIn('subject_id', $own->pluck('subject_id')->unique())
            ->with('subject', 'group')
            ->get()
            ->filter(fn (Course $course) => $own->contains(
                fn (Course $mine) => $mine->subject_id === $course->subject_id && $mine->group_id === $course->group_id,
            ));

        $sections = $courses
            ->groupBy(fn (Course $course) => $course->subject_id.'-'.($course->group_id ?? 0))
            ->map(fn (Collection $set) => $this->teacherSection($set))
            ->sortBy([['subject', 'asc'], ['group', 'asc']])
            ->values()
            ->all();

        return [
            'teacher' => $teacher,
            'cycle' => $cycle,
            'scope' => $cycle->label,
            'status' => $this->status($cycle),
            'sections' => $sections,
        ];
    }

    /**
     * Definitivo cuando están cerrados los módulos que se leen; si no,
     * provisional y por qué.
     *
     * @return array{definitive: bool, label: string, detail: string}
     */
    public function status(Cycle $cycle, string $view = 'definitiva'): array
    {
        $periods = match ($view) {
            'm1' => [1],
            'm2' => [2],
            default => Cycle::MODULES,
        };

        $modules = collect($periods)->mapWithKeys(fn (int $period) => [$period => $this->term->moduleStatus($cycle, $period)]);
        $open = $modules->reject(fn (array $module) => $module['state'] === 'closed');

        if ($open->isEmpty()) {
            return [
                'definitive' => true,
                'label' => __('Definitivo'),
                'detail' => __('Cerrado el :date', ['date' => $this->date($modules->pluck('at')->max())]),
            ];
        }

        if ($open->count() > 1) {
            return [
                'definitive' => false,
                'label' => __('Provisional'),
                'detail' => __('Los módulos :list siguen abiertos', ['list' => $open->keys()->implode(' y ')]),
            ];
        }

        $period = $open->keys()->first();
        $module = $open->first();

        return [
            'definitive' => false,
            'label' => __('Provisional'),
            'detail' => $module['state'] === 'scheduled'
                ? __('El Módulo :n sigue abierto hasta el :date', ['n' => $period, 'date' => $this->date($module['at'])])
                : __('El Módulo :n sigue abierto', ['n' => $period]),
        ];
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(Cycle $cycle, ?Group $group, bool $both): Collection
    {
        return Student::query()
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('cycle_id', $cycle->id)
                ->when($group, fn (Builder $q) => $q->where('group_id', $group->id)))
            ->when($both, fn (Builder $query) => $query->whereRelation('enrollments', 'cycle_id', $cycle->previous_cycle_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * La nota de una línea del boletín según lo que se lee. Está completa
     * cuando tiene las notas de los dos módulos (o de los dos semestres).
     *
     * @param  array<string, mixed>  $line
     * @return array{score: ?float, first: ?float, second: ?float, final: ?float, complete: bool, failed: bool}
     */
    private function cell(array $line, string $view, bool $both): array
    {
        $first = $both ? $line['sem1'] : $line['p1'];
        $second = $both ? $line['sem2'] : $line['p2'];

        [$score, $complete] = match ($view) {
            'm1' => [$first, $first !== null],
            'm2' => [$second, $second !== null],
            default => [$line['final'], $first !== null && $second !== null],
        };

        return [
            'score' => $score,
            'first' => $first,
            'second' => $second,
            'final' => $line['final'],
            'complete' => $complete,
            'failed' => $score !== null && $score < BoletinService::PASSING_SCORE,
        ];
    }

    /**
     * El puesto por promedio, de mayor a menor. Dos promedios iguales comparten
     * puesto y el siguiente lo salta (1, 2, 2, 4). Sin notas no hay puesto.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function rank(array $rows): array
    {
        $ranked = array_filter($rows, fn (array $row) => $row['average'] !== null);
        uasort($ranked, fn (array $a, array $b) => $b['average'] <=> $a['average']);

        $position = 0;
        $rank = 0;
        $previous = null;

        foreach ($ranked as $index => $row) {
            $position++;

            if ($row['average'] !== $previous) {
                $rank = $position;
                $previous = $row['average'];
            }

            $rows[$index]['rank'] = $rank;
        }

        return $rows;
    }

    /**
     * Promedio y cuántos aprueban, pierden o tienen la nota incompleta en cada
     * materia. Solo cuentan los estudiantes a los que les toca la materia.
     *
     * @param  list<array{name: string, components: list<string>}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{students: int, graded: int, average: ?float, passed: int, failed: int, pending: int}>
     */
    private function columnStats(array $columns, array $rows): array
    {
        $stats = [];

        foreach ($columns as $column) {
            $cells = array_values(array_filter(array_map(fn (array $row) => $row['cells'][$column['name']] ?? null, $rows)));
            $scores = array_values(array_filter(array_column($cells, 'score'), fn (?float $score) => $score !== null));
            $failed = count(array_filter($cells, fn (array $cell) => $cell['failed']));

            $stats[$column['name']] = [
                'students' => count($cells),
                'graded' => count($scores),
                'average' => BoletinService::average($scores),
                'passed' => count($scores) - $failed,
                'failed' => $failed,
                'pending' => count(array_filter($cells, fn (array $cell) => ! $cell['complete'])),
            ];
        }

        return $stats;
    }

    /**
     * La lectura rápida: promedio del ciclo, cuántos aprueban todo y cuántos
     * pierden 1, 2 o 3 materias o más, y dónde se pierde más.
     *
     * @param  array<string, array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summary(array $stats, array $rows): array
    {
        $graded = array_values(array_filter($rows, fn (array $row) => $row['average'] !== null));
        $average = BoletinService::average(array_column($graded, 'average'));

        $failing = [1 => 0, 2 => 0, 3 => 0];
        $levels = ['Superior' => 0, 'Alto' => 0, 'Básico' => 0, 'Bajo' => 0];

        foreach ($graded as $row) {
            if ($row['failed'] > 0) {
                $failing[min($row['failed'], 3)]++;
            }

            $levels[$row['performance']]++;
        }

        $hardest = collect($stats)
            ->map(fn (array $stat, string $name) => [...$stat, 'name' => $name])
            ->where('failed', '>', 0)
            ->sortByDesc('failed')
            ->take(3)
            ->values()
            ->all();

        return [
            'students' => count($rows),
            'graded' => count($graded),
            'without_grades' => count($rows) - count($graded),
            'average' => $average,
            'performance' => BoletinService::performance($average),
            'all_passed' => count(array_filter($graded, fn (array $row) => $row['failed'] === 0)),
            'failing' => $failing,
            'failing_total' => array_sum($failing),
            'levels' => $levels,
            'hardest' => $hardest,
            'pending_students' => count(array_filter($rows, fn (array $row) => $row['pending'] > 0)),
            'pending_cells' => array_sum(array_column($rows, 'pending')),
        ];
    }

    /**
     * @param  Collection<int, Course>  $courses  los módulos de una materia en un grupo
     * @return array{subject: string, group: ?string, periods: list<int>, rows: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    private function teacherSection(Collection $courses): array
    {
        $first = $courses->first();
        $byPeriod = $courses->keyBy('period');
        $students = $first->rosterQuery()->get();

        $grades = Grade::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('score', 'course_id'));

        $attendance = $this->attendance->matrix($students->pluck('id')->all(), $courses);

        $rows = $students->map(function (Student $student) use ($byPeriod, $grades, $attendance, $first) {
            $scores = $grades->get($student->id, collect());
            $m1 = $this->score(isset($byPeriod[1]) ? $scores->get($byPeriod[1]->id) : null);
            $m2 = $this->score(isset($byPeriod[2]) ? $scores->get($byPeriod[2]->id) : null);
            $final = BoletinService::average([$m1, $m2]);
            $absences = $attendance[$student->id][$first->subject_id] ?? null;

            return [
                'id' => $student->id,
                'name' => trim("{$student->last_name} {$student->first_name}"),
                'document' => $student->document,
                'm1' => $m1,
                'm2' => $m2,
                'final' => $final,
                'complete' => $m1 !== null && $m2 !== null,
                'failed' => $final !== null && $final < BoletinService::PASSING_SCORE,
                'performance' => BoletinService::performance($final),
                'absences' => $absences['absences'] ?? 0,
                'lost_by_absence' => $absences['lost'] ?? false,
            ];
        })->values()->all();

        $finals = array_values(array_filter(array_column($rows, 'final'), fn (?float $score) => $score !== null));
        $failed = count(array_filter($rows, fn (array $row) => $row['failed']));

        return [
            'subject' => $first->subject->name,
            'group' => $first->group?->name,
            'periods' => $byPeriod->keys()->sort()->values()->all(),
            'rows' => $rows,
            'stats' => [
                'students' => count($rows),
                'm1' => BoletinService::average(array_column($rows, 'm1')),
                'm2' => BoletinService::average(array_column($rows, 'm2')),
                'final' => BoletinService::average($finals),
                'passed' => count($finals) - $failed,
                'failed' => $failed,
                'pending' => count(array_filter($rows, fn (array $row) => ! $row['complete'])),
            ],
        ];
    }

    private function score(float|string|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    private function title(string $view, bool $both): string
    {
        return match (true) {
            $both => __('Definitivas de los dos semestres'),
            $view === 'm1' => __('Notas del Módulo 1'),
            $view === 'm2' => __('Notas del Módulo 2'),
            default => __('Definitivas'),
        };
    }

    private function scope(Cycle $cycle, ?Group $group, bool $both): string
    {
        $scope = $both && $cycle->previousCycle
            ? "{$cycle->name} · {$cycle->previousCycle->year}-{$cycle->previousCycle->semester} y {$cycle->year}-{$cycle->semester}"
            : $cycle->label;

        return $group ? "{$scope} · {$group->name}" : $scope;
    }

    private function date(?CarbonInterface $date): string
    {
        return $date?->translatedFormat('j \d\e F \d\e Y') ?? '';
    }
}
