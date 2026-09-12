<?php

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'iete-import-'.uniqid();
    File::makeDirectory($this->base.DIRECTORY_SEPARATOR.'2026-2', recursive: true);
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

/**
 * Escribe un csv de matrícula con el formato que exporta Moodle.
 *
 * @param  list<array<string, string>>  $rows
 */
function matriculas(string $base, string $name, array $rows): void
{
    $lines = [implode(';', array_keys($rows[0]))];

    foreach ($rows as $row) {
        $lines[] = implode(';', array_values($row));
    }

    File::put($base.DIRECTORY_SEPARATOR.'2026-2'.DIRECTORY_SEPARATOR.$name, implode("\n", $lines)."\n");
}

function importar(string $base): void
{
    $seeder = app(AcademicStructureSeeder::class);
    $seeder->basePath = $base;
    $seeder->run();
}

/**
 * @return array<string, string>
 */
function estudiante(string $email, string $document, string $cohort, string $group, string $course): array
{
    return [
        'username' => strtok($email, '@'),
        'firstname' => 'ANA',
        'lastname' => 'GOMEZ',
        'idnumber' => $document,
        'phone1' => '3000000000',
        'email' => $email,
        'lang' => 'es-CO',
        'city' => 'Tauramena',
        'country' => 'CO',
        'cohort1' => $cohort,
        'group1' => $group,
        'course1' => $course,
    ];
}

describe('ciclos y grupos', function () {
    it('junta las dos cohortes de un mismo ciclo en un solo ciclo con dos grupos', function () {
        matriculas($this->base, 'Ciclo4B1.csv', [
            estudiante('uno@iellano.com', '111', 'Ciclo4BG1S22026', 'Ciclo 4B1', 'C4B1MATM1S22026'),
        ]);
        matriculas($this->base, 'Ciclo4B2.csv', [
            estudiante('dos@iellano.com', '222', 'Ciclo4BG2S22026', 'Ciclo 4B2', 'C4B2MATM1S22026'),
        ]);

        importar($this->base);

        $cycle = Cycle::where('code', 'Ciclo4BS22026')->sole();

        expect(Cycle::count())->toBe(1)
            ->and($cycle->level)->toBe('4B')
            ->and($cycle->semester)->toBe(2)
            ->and($cycle->year)->toBe(2026)
            ->and($cycle->groups->pluck('name')->sort()->values()->all())->toBe(['Ciclo 4B1', 'Ciclo 4B2']);
    });

    it('deja cada curso amarrado a su grupo cuando el ciclo tiene dos', function () {
        matriculas($this->base, 'Ciclo5-1.csv', [
            estudiante('uno@iellano.com', '111', 'Ciclo51S22026', 'Ciclo 5-1', 'C51MATM1S22026'),
        ]);
        matriculas($this->base, 'Ciclo5-2.csv', [
            estudiante('dos@iellano.com', '222', 'Ciclo52S22026', 'Ciclo 5-2', 'C52MATM1S22026'),
        ]);

        importar($this->base);

        expect(Cycle::where('code', 'Ciclo5S22026')->exists())->toBeTrue();

        $courses = Course::with('group')->get()->keyBy('code');

        expect($courses)->toHaveCount(2)
            ->and($courses['C51MATM1S22026']->group->name)->toBe('Ciclo 5-1')
            ->and($courses['C52MATM1S22026']->group->name)->toBe('Ciclo 5-2');
    });

    it('libera del grupo al curso que cubre todo el ciclo', function () {
        matriculas($this->base, 'Ciclo6.csv', [
            estudiante('uno@iellano.com', '111', 'Ciclo6S22026', 'Ciclo 6', 'C6MATM1S22026'),
        ]);

        importar($this->base);

        expect(Course::sole()->group_id)->toBeNull()
            ->and(Group::sole()->name)->toBe('Ciclo 6');
    });
});

describe('códigos de curso', function () {
    it('lee el periodo y la materia del código con sufijo de semestre y año', function () {
        matriculas($this->base, 'Ciclo6.csv', [
            [...estudiante('uno@iellano.com', '111', 'Ciclo6S22026', 'Ciclo 6', 'C6FILM1S22026'), 'course2' => 'C6QUIM2S22026'],
        ]);

        importar($this->base);

        $courses = Course::with('subject')->get()->keyBy('code');

        expect($courses['C6FILM1S22026']->period)->toBe(1)
            ->and($courses['C6FILM1S22026']->subject->code)->toBe('FIL')
            ->and($courses['C6QUIM2S22026']->period)->toBe(2)
            ->and($courses['C6QUIM2S22026']->subject->code)->toBe('QUI');
    });

    it('sigue leyendo los códigos sin sufijo de los semestres anteriores', function () {
        matriculas($this->base, 'Ciclo6.csv', [
            estudiante('uno@iellano.com', '111', 'Ciclo6S12026', 'Ciclo 6', 'C6MATM2'),
        ]);

        importar($this->base);

        expect(Course::sole()->period)->toBe(2);
    });
});

describe('identidad del estudiante', function () {
    it('reconoce por documento a quien llega con otro correo y le actualiza el correo', function () {
        $student = Student::create([
            'document' => '1118198961',
            'first_name' => 'MARIANA',
            'last_name' => 'PEÑA MARTINEZ',
            'email' => 'null7@iellano.com',
        ]);

        matriculas($this->base, 'Ciclo3B.csv', [
            estudiante('mariana@gmail.com', '1118198961', 'Ciclo3BS22026', 'Ciclo 3B', 'C3BMATM1S22026'),
        ]);

        importar($this->base);

        expect(Student::count())->toBe(1)
            ->and($student->fresh()->email)->toBe('mariana@gmail.com')
            ->and(Enrollment::where('student_id', $student->id)->count())->toBe(1);
    });

    it('no mezcla a dos estudiantes distintos sin documento', function () {
        matriculas($this->base, 'Ciclo3B.csv', [
            estudiante('uno@iellano.com', '', 'Ciclo3BS22026', 'Ciclo 3B', 'C3BMATM1S22026'),
            estudiante('dos@iellano.com', '', 'Ciclo3BS22026', 'Ciclo 3B', 'C3BMATM1S22026'),
        ]);

        importar($this->base);

        expect(Student::count())->toBe(2);
    });
});

describe('boletín de ciclo completo', function () {
    it('enlaza el semestre 2 con el semestre 1 del mismo ciclo', function () {
        $primero = Cycle::create([
            'code' => 'Ciclo3AS12026', 'level' => '3A', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 3A',
        ]);

        matriculas($this->base, 'Ciclo3B.csv', [
            estudiante('uno@iellano.com', '111', 'Ciclo3BS22026', 'Ciclo 3B', 'C3BMATM1S22026'),
        ]);

        importar($this->base);

        expect(Cycle::where('code', 'Ciclo3BS22026')->sole()->previous_cycle_id)->toBe($primero->id);
    });

    it('no enlaza nada cuando el semestre anterior no está importado', function () {
        matriculas($this->base, 'Ciclo3B.csv', [
            estudiante('uno@iellano.com', '111', 'Ciclo3BS22026', 'Ciclo 3B', 'C3BMATM1S22026'),
        ]);

        importar($this->base);

        expect(Cycle::where('code', 'Ciclo3BS22026')->sole()->previous_cycle_id)->toBeNull();
    });
});
