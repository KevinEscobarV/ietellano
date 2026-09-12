<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AcademicStructureSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const SUBJECTS = [
        'CN' => 'Ciencias Naturales',
        'CS' => 'Ciencias Sociales',
        'EA' => 'Ed. Artística',
        'EF' => 'Ed. Física',
        'ER' => 'Ética y Religión',
        'FIL' => 'Filosofía',
        'FIS' => 'Física',
        'ING' => 'Inglés',
        'LEN' => 'Lengua Castellana',
        'MAT' => 'Matemáticas',
        'PV' => 'Proyecto de Vida',
        'QUI' => 'Química',
        'TEC' => 'Tecnología e Informática',
    ];

    /**
     * Three-letter tokens are matched before two-letter ones to avoid substring collisions.
     *
     * @var list<string>
     */
    private const SUBJECT_MATCH_ORDER = ['FIL', 'FIS', 'ING', 'LEN', 'MAT', 'QUI', 'TEC', 'CN', 'CS', 'EA', 'EF', 'ER', 'PV'];

    /**
     * Carpetas de `database/data` con los csv de matrícula, en orden cronológico.
     * Un semestre nuevo es una línea más.
     *
     * @var list<string>
     */
    private const DATA_DIRS = [
        'Sistema de calificaciones Semipresencial/Estudiantes csv M1',
        'Sistema de calificaciones Semipresencial/Estudiantes csv M2',
        'Sistema de calificaciones Semipresencial/Estudiantes csv con documentos M2',
        '2026-2',
    ];

    /**
     * Desde 2026-2 la cohorte de Moodle trae el grupo pegado al nivel
     * (Ciclo4BG1S22026, Ciclo51S22026). Académicamente siguen siendo un solo
     * ciclo con dos grupos, que es como se modeló el Ciclo 5 en 2026-1.
     *
     * @var array<string, string>
     */
    private const COHORT_ALIASES = [
        'Ciclo4BG1S22026' => 'Ciclo4BS22026',
        'Ciclo4BG2S22026' => 'Ciclo4BS22026',
        'Ciclo51S22026' => 'Ciclo5S22026',
        'Ciclo52S22026' => 'Ciclo5S22026',
    ];

    /**
     * Ciclos que ocupan dos semestres: el boletín del ciclo completo junta el
     * semestre anterior con el actual.
     *
     * @var array<string, string>
     */
    private const PREVIOUS_CYCLES = [
        'Ciclo3BS22026' => 'Ciclo3AS12026',
        'Ciclo4BS22026' => 'Ciclo4AS12026',
    ];

    /**
     * Raíz de los csv de matrícula. Los tests la apuntan a un directorio con
     * archivos de prueba.
     */
    public ?string $basePath = null;

    /** @var array<string, Subject> */
    private array $subjects = [];

    /** @var array<string, Cycle> */
    private array $cycles = [];

    /** @var array<string, Group> */
    private array $groups = [];

    /** @var array<string, Course> */
    private array $courses = [];

    public function run(): void
    {
        $this->seedSubjects();

        foreach (self::DATA_DIRS as $dir) {
            $path = ($this->basePath ?? database_path('data')).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $dir);

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::files($path) as $file) {
                if (strtolower($file->getExtension()) === 'csv') {
                    $this->importFile($file->getPathname());
                }
            }
        }

        $this->normalizeCourseGroups();
        $this->linkPreviousCycles();
        $this->seedAreas();
    }

    private function seedSubjects(): void
    {
        foreach (self::SUBJECTS as $code => $name) {
            $this->subjects[$code] = Subject::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function importFile(string $path): void
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return;
        }

        $header = fgetcsv($handle, 0, ';', '"', '');

        if ($header === false) {
            fclose($handle);

            return;
        }

        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        $header = array_map(fn ($value) => trim((string) $value), $header);
        $index = array_flip($header);

        $courseColumns = [];
        $groupColumns = [];

        foreach ($header as $position => $name) {
            if (preg_match('/^course\d+$/', $name)) {
                $courseColumns[] = $position;
            } elseif (preg_match('/^group\d+$/', $name)) {
                $groupColumns[] = $position;
            }
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            if (count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $this->importRow($row, $index, $courseColumns, $groupColumns);
        }

        fclose($handle);
    }

    /**
     * @param  array<int, int|string|null>  $row
     * @param  array<string, int>  $index
     * @param  list<int>  $courseColumns
     * @param  list<int>  $groupColumns
     */
    private function importRow(array $row, array $index, array $courseColumns, array $groupColumns): void
    {
        $value = function (string $column) use ($row, $index): ?string {
            if (! isset($index[$column])) {
                return null;
            }

            $raw = isset($row[$index[$column]]) ? trim((string) $row[$index[$column]]) : '';

            return ($raw === '' || strtolower($raw) === 'null') ? null : $raw;
        };

        $email = $value('email');
        $cohort = $value('cohort1');

        if ($email === null || $cohort === null) {
            return;
        }

        $cycle = $this->resolveCycle($cohort);

        if ($cycle === null) {
            return;
        }

        $groupName = null;

        foreach ($groupColumns as $position) {
            $candidate = isset($row[$position]) ? trim((string) $row[$position]) : '';

            if ($candidate !== '') {
                $groupName = $candidate;
                break;
            }
        }

        $group = $groupName !== null ? $this->resolveGroup($cycle, $groupName) : null;
        $student = $this->resolveStudent($email, $value);

        Enrollment::firstOrCreate(
            ['student_id' => $student->id, 'cycle_id' => $cycle->id],
            ['group_id' => $group?->id],
        );

        foreach ($courseColumns as $position) {
            $code = isset($row[$position]) ? trim((string) $row[$position]) : '';

            if ($code !== '') {
                $this->resolveCourse($code, $cycle, $group);
            }
        }
    }

    private function resolveCycle(string $cohort): ?Cycle
    {
        $code = self::COHORT_ALIASES[$cohort] ?? $cohort;

        if (isset($this->cycles[$code])) {
            return $this->cycles[$code];
        }

        if (! preg_match('/^Ciclo(.+?)S(\d)(\d{4})$/', $code, $matches)) {
            return null;
        }

        [, $level, $semester, $year] = $matches;

        return $this->cycles[$code] = Cycle::firstOrCreate(
            ['code' => $code],
            ['level' => $level, 'semester' => (int) $semester, 'year' => (int) $year, 'name' => "Ciclo {$level}"],
        );
    }

    private function resolveGroup(Cycle $cycle, string $name): Group
    {
        $key = $cycle->id.'|'.$name;

        return $this->groups[$key] ??= Group::firstOrCreate(['cycle_id' => $cycle->id, 'name' => $name]);
    }

    /**
     * @param  callable(string): ?string  $value
     */
    private function resolveStudent(string $email, callable $value): Student
    {
        $document = $value('idnumber');

        // El correo cambia de un semestre a otro: en 2026-1 varios entraron con
        // un placeholder nullN@iellano.com y este semestre ya traen el suyo. Es
        // el documento el que identifica a la persona.
        $student = Student::where('email', $email)->first()
            ?? ($document !== null ? Student::where('document', $document)->first() : null)
            ?? new Student;

        $student->email = $email;
        $student->first_name = $value('firstname') ?? $student->first_name ?? '';
        $student->last_name = $value('lastname') ?? $student->last_name ?? '';
        $student->username = $value('username') ?? $student->username;
        $student->phone = $value('phone1') ?? $student->phone;
        $student->city = $value('city') ?? $student->city;
        $student->country = $value('country') ?? $student->country;
        $student->lang = $value('lang') ?? $student->lang;

        if ($document !== null) {
            $student->document = $document;
        }

        $student->save();

        return $student;
    }

    private function resolveCourse(string $code, Cycle $cycle, ?Group $group): ?Course
    {
        if (isset($this->courses[$code])) {
            return $this->courses[$code];
        }

        // Desde 2026-2 el código arrastra el semestre y el año: C5MATM1 -> C3BMATM1S22026.
        $token = preg_replace('/S(\d)(\d{4})$/', '', $code);

        if (! preg_match('/M(\d)$/', $token, $matches)) {
            return null;
        }

        $subject = $this->matchSubject($token);

        if ($subject === null) {
            return null;
        }

        return $this->courses[$code] = Course::firstOrCreate(
            ['code' => $code],
            [
                'cycle_id' => $cycle->id,
                'subject_id' => $subject->id,
                'group_id' => $group?->id,
                'period' => (int) $matches[1],
            ],
        );
    }

    private function matchSubject(string $code): ?Subject
    {
        foreach (self::SUBJECT_MATCH_ORDER as $token) {
            if (str_contains($code, $token)) {
                return $this->subjects[$token];
            }
        }

        return null;
    }

    private function normalizeCourseGroups(): void
    {
        Course::all()
            ->groupBy(fn (Course $course) => $course->cycle_id.'|'.$course->subject_id.'|'.$course->period)
            ->each(function ($courses): void {
                if ($courses->count() === 1) {
                    $courses->first()->update(['group_id' => null]);
                }
            });
    }

    private function linkPreviousCycles(): void
    {
        foreach (self::PREVIOUS_CYCLES as $code => $previousCode) {
            $cycle = Cycle::where('code', $code)->first();
            $previous = Cycle::where('code', $previousCode)->first();

            if ($cycle !== null && $previous !== null) {
                $cycle->update(['previous_cycle_id' => $previous->id]);
            }
        }
    }

    private function seedAreas(): void
    {
        $fisica = $this->subjects['FIS'] ?? null;
        $quimica = $this->subjects['QUI'] ?? null;

        if ($fisica === null || $quimica === null) {
            return;
        }

        Cycle::query()->whereIn('level', ['5', '6'])->each(function (Cycle $cycle) use ($fisica, $quimica): void {
            $area = Area::firstOrCreate(
                ['cycle_id' => $cycle->id, 'name' => 'Ciencias Naturales'],
                ['code' => 'CN', 'display_order' => 0],
            );

            $area->subjects()->syncWithoutDetaching([$fisica->id, $quimica->id]);
        });
    }
}
