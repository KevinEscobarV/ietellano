<?php

use App\Livewire\Admin\Certificates;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\CertificateService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->cycle = Cycle::create([
        'code' => 'Ciclo5S12026',
        'level' => '5',
        'semester' => 1,
        'year' => 2026,
        'name' => 'Ciclo 5',
    ]);

    $this->group = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 5', 'code' => 'G1']);

    $this->student = Student::create([
        'document' => '111',
        'first_name' => 'Ana',
        'last_name' => 'Gomez',
        'email' => 'ana@example.com',
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'cycle_id' => $this->cycle->id,
        'group_id' => $this->group->id,
    ]);

    /**
     * Pone las notas de un ciclo por código de asignatura, creando la materia y
     * el curso si hacen falta. Un null deja la asignatura sin calificar.
     *
     * @param  array<string, ?float>  $scores
     */
    $this->calificar = function (array $scores, ?Cycle $cycle = null) {
        $cycle ??= $this->cycle;

        foreach ($scores as $code => $score) {
            $subject = Subject::firstOrCreate(['code' => $code], ['name' => $code]);

            $course = Course::firstOrCreate(
                ['code' => "C5{$code}M1-{$cycle->id}", 'cycle_id' => $cycle->id],
                ['subject_id' => $subject->id, 'period' => 1],
            );

            if ($score !== null) {
                Grade::updateOrCreate(
                    ['course_id' => $course->id, 'student_id' => $this->student->id],
                    ['score' => $score],
                );
            }
        }
    };

    $this->certificado = fn (bool $both = false) => app(CertificateService::class)
        ->generate($this->student->fresh(), $this->cycle->fresh(), $both);
});

describe('veredicto del certificado', function () {
    it('aprueba cuando pasó todas las asignaturas', function () {
        ($this->calificar)(['MAT' => 4.0, 'LEN' => 4.0, 'ING' => 4.0, 'CN' => 4.0]);

        $certificate = ($this->certificado)();

        expect($certificate['approved'])->toBeTrue()
            ->and($certificate['failed_subjects'])->toBe([])
            ->and($certificate['verb'])->toBe('Cursó y Aprobó');
    });

    it('reprueba con una asignatura perdida si el promedio no llega a 4', function () {
        ($this->calificar)(['MAT' => 2.0, 'LEN' => 4.5, 'ING' => 4.5, 'CN' => 4.5]);

        $certificate = ($this->certificado)();

        // Pasa el corte de 3.0, pero no el de 4.0 que exige arrastrar una materia.
        expect($certificate['average'])->toBe(3.67)
            ->and($certificate['failed_subjects'])->toBe(['MAT'])
            ->and($certificate['approved'])->toBeFalse()
            ->and($certificate['verb'])->toBe('Cursó y No Aprobó');
    });

    it('aprueba arrastrando una materia si el promedio llega a 4', function () {
        // Áreas: Ciencias Naturales 5.0, Humanidades 5.0, Matemáticas 2.0.
        // El promedio queda en 4.00 exacto, que ya cuenta.
        ($this->calificar)(['MAT' => 2.0, 'LEN' => 5.0, 'ING' => 5.0, 'CN' => 5.0]);

        $certificate = ($this->certificado)();

        expect($certificate['average'])->toBe(4.0)
            ->and($certificate['failed_subjects'])->toBe(['MAT'])
            ->and($certificate['approved'])->toBeTrue()
            ->and($certificate['verb'])->toBe('Cursó y Aprobó');
    });

    it('no la arrastra por una centésima', function () {
        ($this->calificar)(['MAT' => 2.0, 'LEN' => 5.0, 'ING' => 5.0, 'CN' => 4.97]);

        $certificate = ($this->certificado)();

        expect($certificate['average'])->toBe(3.99)
            ->and($certificate['approved'])->toBeFalse();
    });

    it('no arrastra dos materias por alto que sea el promedio', function () {
        ($this->calificar)([
            'MAT' => 2.9, 'FIS' => 2.9, 'QUI' => 5.0,
            'LEN' => 5.0, 'ING' => 5.0, 'CN' => 5.0, 'CS' => 5.0,
            'EA' => 5.0, 'EF' => 5.0, 'ER' => 5.0, 'PV' => 5.0,
            'TEC' => 5.0, 'FIL' => 5.0,
        ]);

        $certificate = ($this->certificado)();

        expect($certificate['average'])->toBeGreaterThan(4.0)
            ->and($certificate['failed_subjects'])->toBe(['FIS', 'MAT'])
            ->and($certificate['approved'])->toBeFalse();
    });

    it('no deja que un área tape la asignatura perdida que lleva dentro', function () {
        // Humanidades es Lengua e Inglés: con 4.0 y 2.0 el área sale en 3.0 y
        // pasaría, pero Inglés está perdido.
        ($this->calificar)(['MAT' => 4.0, 'LEN' => 4.0, 'ING' => 2.0, 'CN' => 4.0]);

        $certificate = ($this->certificado)();

        $humanidades = collect($certificate['lines'])
            ->firstWhere('name', 'Humanidades – Lengua Castellana / Idioma Inglés');

        expect($humanidades['cal'])->toBe(3.0)
            ->and($certificate['failed_subjects'])->toBe(['ING'])
            ->and($certificate['approved'])->toBeFalse();
    });

    it('una asignatura sin calificar no reprueba a nadie', function () {
        ($this->calificar)(['MAT' => null, 'LEN' => 4.0, 'ING' => 4.0, 'CN' => 4.0]);

        $certificate = ($this->certificado)();

        expect($certificate['failed_subjects'])->toBe([])
            ->and($certificate['approved'])->toBeTrue();
    });

    it('sin ninguna nota no aprueba', function () {
        ($this->calificar)(['MAT' => null, 'LEN' => null]);

        expect(($this->certificado)()['approved'])->toBeFalse();
    });

    it('promedia los dos periodos de la asignatura antes de decidir', function () {
        ($this->calificar)(['MAT' => 2.0, 'LEN' => 4.0, 'ING' => 4.0, 'CN' => 4.0]);

        // El segundo periodo de Matemáticas la levanta: 2.0 y 4.0 dan 3.0.
        $mat = Subject::where('code', 'MAT')->sole();

        $segundo = Course::create([
            'code' => 'C5MATM2',
            'cycle_id' => $this->cycle->id,
            'subject_id' => $mat->id,
            'period' => 2,
        ]);

        Grade::create(['course_id' => $segundo->id, 'student_id' => $this->student->id, 'score' => 4.0]);

        $certificate = ($this->certificado)();

        expect($certificate['failed_subjects'])->toBe([])
            ->and($certificate['approved'])->toBeTrue();
    });
});

describe('certificado de los dos semestres', function () {
    beforeEach(function () {
        $this->previous = Cycle::create([
            'code' => 'Ciclo4AS12026',
            'level' => '4A',
            'semester' => 1,
            'year' => 2026,
            'name' => 'Ciclo 4A',
        ]);

        $this->cycle->update(['previous_cycle_id' => $this->previous->id, 'closed_at' => now()]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'cycle_id' => $this->previous->id,
        ]);
    });

    it('no combina mientras el segundo semestre siga abierto', function () {
        ($this->calificar)(['MAT' => 4.0, 'LEN' => 4.0], $this->previous);
        $this->cycle->update(['closed_at' => null]);

        $certificate = ($this->certificado)(true);

        // Con el semestre 2 en curso certificaría un ciclo a medias.
        expect(app(CertificateService::class)->canCombine($this->cycle->fresh()))->toBeFalse()
            ->and($certificate['both'])->toBeFalse()
            ->and($certificate['grade_label'])->toBe('CICLO V');
    });

    it('habla de un año lectivo si los dos semestres son del mismo año', function () {
        ($this->calificar)(['MAT' => 4.0]);

        $certificate = ($this->certificado)(true);

        expect($certificate['year_label'])->toBe('2026')
            ->and($certificate['spans_years'])->toBeFalse();
    });

    it('nombra los dos años cuando el ciclo cruza de año', function () {
        $this->previous->update(['year' => 2025, 'semester' => 2]);
        ($this->calificar)(['MAT' => 4.0]);

        $certificate = ($this->certificado)(true);

        expect($certificate['year_label'])->toBe('2025 y 2026')
            ->and($certificate['spans_years'])->toBeTrue();
    });

    it('suma los dos semestres antes de dar por perdida una asignatura', function () {
        ($this->calificar)(['MAT' => 2.0, 'LEN' => 4.0, 'ING' => 4.0], $this->previous);
        ($this->calificar)(['MAT' => 4.0, 'LEN' => 4.0, 'ING' => 4.0]);

        $certificate = ($this->certificado)(true);

        expect($certificate['both'])->toBeTrue()
            ->and($certificate['failed_subjects'])->toBe([])
            ->and($certificate['approved'])->toBeTrue();
    });

    it('reprueba si entre los dos semestres la asignatura no levanta', function () {
        ($this->calificar)(['MAT' => 2.0, 'LEN' => 4.5, 'ING' => 4.5], $this->previous);
        ($this->calificar)(['MAT' => 2.5, 'LEN' => 4.5, 'ING' => 4.5]);

        $certificate = ($this->certificado)(true);

        expect($certificate['average'])->toBeGreaterThanOrEqual(3.0)
            ->and($certificate['failed_subjects'])->toBe(['MAT'])
            ->and($certificate['approved'])->toBeFalse();
    });
});

describe('grado que nombra el certificado', function () {
    beforeEach(function () {
        $this->primero = Cycle::create([
            'code' => 'Ciclo3AS12026', 'level' => '3A', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 3A',
        ]);

        $this->segundo = Cycle::create([
            'code' => 'Ciclo3BS22026', 'level' => '3B', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 3B',
            'previous_cycle_id' => $this->primero->id, 'closed_at' => now(),
        ]);

        $this->rotulo = fn (Cycle $cycle, bool $both = false) => app(CertificateService::class)
            ->generate($this->student, $cycle, $both)['grade_label'];
    });

    it('escribe el semestre en vez de la letra del nivel', function () {
        expect(($this->rotulo)($this->primero))->toBe('CICLO III – Semestre 1')
            ->and(($this->rotulo)($this->segundo))->toBe('CICLO III – Semestre 2');
    });

    it('nombra el ciclo completo cuando junta los dos semestres', function () {
        expect(($this->rotulo)($this->segundo, true))->toBe('CICLO III');
    });

    it('deja sin semestre a los ciclos de uno solo', function () {
        expect(($this->rotulo)($this->cycle))->toBe('CICLO V');
    });
});

describe('pantalla de certificados', function () {
    beforeEach(function () {
        Role::findOrCreate('admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->siguiente = Cycle::create([
            'code' => 'Ciclo5S22026', 'level' => '5', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 5 siguiente',
            'previous_cycle_id' => $this->cycle->id,
        ]);
    });

    it('lista el primer semestre aunque ya tenga continuación', function () {
        Livewire::actingAs($this->admin)
            ->test(Certificates::class)
            ->assertSee('Ciclo 5 · 2026-1')
            ->assertSee('Ciclo 5 siguiente · 2026-2');
    });

    it('certifica a quien se quedó en el primer semestre', function () {
        ($this->calificar)(['MAT' => 4.0]);

        Livewire::actingAs($this->admin)
            ->test(Certificates::class)
            ->set('cycleId', (string) $this->cycle->id)
            ->set('studentId', (string) $this->student->id)
            ->assertSee('Gomez, Ana')
            ->assertSee('CICLO V');
    });

    it('no deja juntar los semestres hasta que se cierre el segundo', function () {
        Livewire::actingAs($this->admin)
            ->test(Certificates::class)
            ->set('cycleId', (string) $this->siguiente->id)
            ->set('bothSemesters', true)
            ->assertSee('Se habilita cuando este semestre esté cerrado')
            ->assertViewHas('combining', false);

        $this->siguiente->update(['closed_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(Certificates::class)
            ->set('cycleId', (string) $this->siguiente->id)
            ->set('bothSemesters', true)
            ->assertDontSee('Se habilita cuando este semestre esté cerrado')
            ->assertViewHas('combining', true);
    });
});
