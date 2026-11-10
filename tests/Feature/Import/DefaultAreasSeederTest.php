<?php

use App\Models\Area;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Services\BoletinService;
use App\Services\CertificateService;
use Database\Seeders\DefaultAreasSeeder;

beforeEach(function () {
    /**
     * Crea un ciclo que dicta las materias dadas, una por código.
     *
     * @param  list<string>  $codes
     */
    $this->ciclo = function (string $level, array $codes): Cycle {
        $cycle = Cycle::create(['code' => "Ciclo{$level}S22026", 'level' => $level, 'semester' => 2, 'year' => 2026, 'name' => "Ciclo {$level}"]);

        foreach ($codes as $code) {
            Course::create([
                'code' => "C{$level}{$code}M1",
                'cycle_id' => $cycle->id,
                'subject_id' => Subject::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'period' => 1,
            ]);
        }

        return $cycle;
    };

    /**
     * @return array<string, list<string>> nombre del área => códigos de sus materias
     */
    $this->areas = fn (Cycle $cycle): array => $cycle->areas()->with('subjects')->get()
        ->mapWithKeys(fn (Area $area) => [$area->name => $area->subjects->pluck('code')->sort()->values()->all()])
        ->all();
});

it('crea Humanidades en cada ciclo que dicta Lengua e Inglés', function () {
    $tercero = ($this->ciclo)('3B', ['LEN', 'ING', 'MAT', 'CN']);
    $quinto = ($this->ciclo)('5', ['LEN', 'ING', 'FIS', 'QUI']);

    app(DefaultAreasSeeder::class)->run();

    expect(($this->areas)($tercero))->toBe(['Humanidades' => ['ING', 'LEN']])
        ->and(($this->areas)($quinto))->toEqualCanonicalizing(['Ciencias Naturales' => ['FIS', 'QUI'], 'Humanidades' => ['ING', 'LEN']]);
});

it('no crea un área a medias: el ciclo tiene que dictar todas sus materias', function () {
    $cycle = ($this->ciclo)('6', ['LEN', 'FIS', 'MAT']);

    app(DefaultAreasSeeder::class)->run();

    expect(Area::where('cycle_id', $cycle->id)->count())->toBe(0);
});

it('se puede volver a correr sin duplicar y respeta el área que ya existe', function () {
    $cycle = ($this->ciclo)('5', ['LEN', 'ING']);

    Area::create(['cycle_id' => $cycle->id, 'name' => 'Humanidades', 'code' => 'HUMANIDADES', 'display_order' => 7]);

    app(DefaultAreasSeeder::class)->run();
    app(DefaultAreasSeeder::class)->run();

    $area = Area::where('cycle_id', $cycle->id)->sole();

    expect($area->code)->toBe('HUMANIDADES')
        ->and($area->display_order)->toBe(7)
        ->and($area->subjects()->count())->toBe(2);
});

it('deja el boletín con la misma nota y el mismo promedio del certificado', function () {
    $cycle = ($this->ciclo)('5', ['LEN', 'ING', 'MAT']);
    $student = Student::create(['document' => '111', 'first_name' => 'Ana', 'last_name' => 'Gomez', 'email' => 'ana@example.com']);
    Enrollment::create(['student_id' => $student->id, 'cycle_id' => $cycle->id]);

    foreach (['LEN' => 3.0, 'ING' => 4.0, 'MAT' => 5.0] as $code => $score) {
        Grade::create(['course_id' => Course::where('code', "C5{$code}M1")->value('id'), 'student_id' => $student->id, 'score' => $score]);
    }

    // Sin el área, Lengua e Inglés van sueltas y el promedio sale distinto.
    expect(app(BoletinService::class)->generate($student, $cycle)['overall'])->toBe(4.0);

    app(DefaultAreasSeeder::class)->run();

    $boletin = app(BoletinService::class)->generate($student, $cycle);
    $certificate = app(CertificateService::class)->generate($student, $cycle);
    $humanidades = collect($boletin['lines'])->firstWhere('name', 'Humanidades');

    expect(array_column($boletin['lines'], 'name'))->toBe(['Humanidades', 'MAT'])
        ->and($humanidades['final'])->toBe(3.5)
        ->and(array_column($humanidades['components'], 'final', 'name'))->toEqualCanonicalizing(['LEN' => 3.0, 'ING' => 4.0])
        ->and($boletin['overall'])->toBe(4.25)
        ->and($boletin['overall'])->toBe($certificate['average']);
});
