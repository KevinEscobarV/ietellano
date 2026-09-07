<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Support\XlsxReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('grades:import-previous {file : xlsx path under database/data} {--target=4B : level of the cycle whose previous semester this is} {--level=4A : level for the historical cycle} {--year=2025} {--semester=2} {--name=}')]
#[Description('Import a closed prior-semester grade sheet as a historical cycle and link it to the current cycle.')]
class ImportPreviousSemesterGrades extends Command
{
    /**
     * File column header => subject code(s) to average into one final.
     */
    private const COLUMN_MAP = [
        'NATURALES' => ['CN'],
        'SOCIALES' => ['CS'],
        'LENGUAJE' => ['LEN'],
        'INGLES' => ['ING'],
        'ARTISTICA' => ['EA'],
        'ETICA/RELIGION' => ['ER'],
        'EDUFISICA' => ['EF'],
        'MATEMATICAS' => ['MAT'],
        'TECNOLOGIA' => ['TEC'],
    ];

    public function handle(XlsxReader $reader): int
    {
        $path = database_path('data/'.$this->argument('file'));

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $target = Cycle::where('level', $this->option('target'))->first();

        if ($target === null) {
            $this->error("Target cycle not found (level {$this->option('target')}).");

            return self::FAILURE;
        }

        $rows = $reader->rows($path);
        $this->info('Rows in file: '.count($rows));

        return DB::transaction(function () use ($rows, $target) {
            $cycle = $this->historicalCycle();
            $courses = $this->courses($cycle);

            $matched = 0;
            $missing = [];

            foreach ($rows as $row) {
                $student = $this->resolveStudent($row);

                if ($student === null) {
                    $email = strtolower(trim($row['Dirección de correo'] ?? ''));
                    $missing[] = ($row['Apellido(s)'] ?? '').' '.($row['Nombre'] ?? '')." <{$email}>";

                    continue;
                }

                $this->importStudentRow($student, $row, $cycle, $courses);
                $matched++;
            }

            $target->update(['previous_cycle_id' => $cycle->id]);

            $this->info("Historical cycle: {$cycle->name} (id {$cycle->id})");
            $this->info("Students matched: {$matched}  |  linked to: {$target->name}");

            if ($missing !== []) {
                $this->warn('Unmatched rows ('.count($missing).'):');
                foreach ($missing as $m) {
                    $this->line("  - {$m}");
                }
            }

            return self::SUCCESS;
        });
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveStudent(array $row): ?Student
    {
        $email = strtolower(trim($row['Dirección de correo'] ?? ''));

        return $email !== '' ? Student::whereRaw('LOWER(email) = ?', [$email])->first() : null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, array<int, Course>>  $courses
     */
    private function importStudentRow(Student $student, array $row, Cycle $cycle, array $courses): void
    {
        Enrollment::updateOrCreate(
            ['student_id' => $student->id, 'cycle_id' => $cycle->id],
            ['group_id' => null],
        );

        foreach (self::COLUMN_MAP as $column => $codes) {
            $value = $this->columnValue($row, $column);

            foreach ($codes as $code) {
                foreach ([1, 2] as $period) {
                    Grade::updateOrCreate(
                        ['course_id' => $courses[$code][$period]->id, 'student_id' => $student->id],
                        ['score' => $value],
                    );
                }
            }
        }
    }

    private function historicalCycle(): Cycle
    {
        $level = $this->option('level');
        $year = (int) $this->option('year');
        $name = $this->option('name') ?: "Ciclo {$level} {$year}";

        return Cycle::updateOrCreate(
            ['code' => "Ciclo{$level}Prev{$year}"],
            [
                'level' => $level,
                'semester' => (int) $this->option('semester'),
                'year' => $year,
                'name' => $name,
            ],
        );
    }

    /**
     * @return array<string, array<int, Course>> subject code => period => course
     */
    private function courses(Cycle $cycle): array
    {
        $codes = array_unique(array_merge(...array_values(self::COLUMN_MAP)));
        $courses = [];

        foreach ($codes as $code) {
            $subject = Subject::where('code', $code)->first();

            if ($subject === null) {
                $this->warn("Subject not found: {$code} (skipped)");

                continue;
            }

            foreach ([1, 2] as $period) {
                $courses[$code][$period] = Course::updateOrCreate(
                    ['code' => "{$cycle->code}-{$code}-P{$period}"],
                    [
                        'cycle_id' => $cycle->id,
                        'subject_id' => $subject->id,
                        'group_id' => null,
                        'teacher_id' => null,
                        'period' => $period,
                    ],
                );
            }
        }

        return $courses;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function columnValue(array $row, string $column): ?float
    {
        $columns = $column === 'ETICA/RELIGION' ? ['ETICA', 'RELIGION'] : [$column];
        $values = [];

        foreach ($columns as $key) {
            $raw = trim($row[$key] ?? '');

            if ($raw !== '' && is_numeric($raw)) {
                $values[] = (float) $raw;
            }
        }

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }
}
