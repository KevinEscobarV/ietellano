<?php

use App\Livewire\Admin\Boletines;
use App\Models\Area;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\BoletinService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->cycle = Cycle::create([
        'code' => 'C5-2026-1',
        'level' => '5',
        'semester' => 1,
        'year' => 2026,
        'name' => 'Ciclo 5',
    ]);

    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematicas']);

    $first = Course::create(['code' => 'C5MATM1', 'cycle_id' => $this->cycle->id, 'subject_id' => $subject->id, 'period' => 1]);
    $second = Course::create(['code' => 'C5MATM2', 'cycle_id' => $this->cycle->id, 'subject_id' => $subject->id, 'period' => 2]);

    $this->student = Student::create([
        'document' => '1118544017',
        'first_name' => 'LINETH YULIANA',
        'last_name' => 'AREVALO HERNANDEZ',
        'email' => 'lineth@example.com',
    ]);

    Enrollment::create(['student_id' => $this->student->id, 'cycle_id' => $this->cycle->id]);

    Grade::create(['course_id' => $first->id, 'student_id' => $this->student->id, 'score' => 4.0]);
    Grade::create(['course_id' => $second->id, 'student_id' => $this->student->id, 'score' => 3.0]);
});

it('arma el boletín del estudiante elegido', function () {
    Livewire::actingAs($this->admin)
        ->test(Boletines::class)
        ->set('cycleId', (string) $this->cycle->id)
        ->set('studentId', (string) $this->student->id)
        ->assertSee('LINETH YULIANA AREVALO HERNANDEZ')
        ->assertSee('Matematicas')
        ->assertSee('3.50');
});

it('lista el primer semestre aunque ya tenga continuación', function () {
    Cycle::create([
        'code' => 'C5-2026-2',
        'level' => '5',
        'semester' => 2,
        'year' => 2026,
        'name' => 'Ciclo 5 siguiente',
        'previous_cycle_id' => $this->cycle->id,
    ]);

    // Quien no siguió al segundo semestre solo tiene el boletín de este.
    Livewire::actingAs($this->admin)
        ->test(Boletines::class)
        ->assertSet('semester', '2')
        ->set('semester', '1')
        ->assertSet('cycleId', (string) $this->cycle->id)
        ->set('studentId', (string) $this->student->id)
        ->assertSee('3.50');
});

describe('recorrido semestre, ciclo y estudiante', function () {
    beforeEach(function () {
        $this->other = Student::create([
            'document' => '99887766',
            'first_name' => 'José',
            'last_name' => 'Ñáñez',
            'email' => 'jose@example.com',
        ]);

        Enrollment::create(['student_id' => $this->other->id, 'cycle_id' => $this->cycle->id]);

        $this->names = fn (Testable $page) => $page->viewData('roster')->pluck('name')->all();
    });

    it('abre con el ciclo elegido y la lista de sus estudiantes', function () {
        $page = Livewire::actingAs($this->admin)->test(Boletines::class)
            ->assertSet('cycleId', (string) $this->cycle->id);

        expect(($this->names)($page))->toBe(['AREVALO HERNANDEZ LINETH YULIANA', 'Ñáñez José']);
    });

    it('busca sin tildes, por nombre o por documento', function () {
        $page = Livewire::actingAs($this->admin)->test(Boletines::class)->set('search', 'nanez jose');

        expect(($this->names)($page))->toBe(['Ñáñez José']);

        $page->set('search', '1118544');

        expect(($this->names)($page))->toBe(['AREVALO HERNANDEZ LINETH YULIANA']);
    });

    it('dice cuántas notas tiene cada uno de las que le tocan', function () {
        $roster = Livewire::actingAs($this->admin)->test(Boletines::class)->viewData('roster')->keyBy('id');

        expect($roster[$this->student->id])->toMatchArray(['graded' => 2, 'expected' => 2])
            ->and($roster[$this->other->id])->toMatchArray(['graded' => 0, 'expected' => 2]);
    });

    it('cambiar de ciclo suelta al estudiante y la búsqueda', function () {
        $otro = Cycle::create(['code' => 'C6-2026-1', 'level' => '6', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 6']);

        Livewire::actingAs($this->admin)->test(Boletines::class)
            ->set('studentId', (string) $this->student->id)
            ->set('search', 'lineth')
            ->set('cycleId', (string) $otro->id)
            ->assertSet('studentId', '')
            ->assertSet('search', '')
            ->assertViewHas('boletin', null);
    });

    it('un enlace con el ciclo abre en su semestre', function () {
        Cycle::create(['code' => 'C5-2026-2', 'level' => '5', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 5 siguiente']);

        Livewire::actingAs($this->admin)
            ->withQueryParams(['ciclo' => (string) $this->cycle->id, 'estudiante' => (string) $this->student->id])
            ->test(Boletines::class)
            ->assertSet('semester', '1')
            ->assertSee('3.50');
    });

    it('no arma el boletín de alguien que no está en el ciclo', function () {
        $ajeno = Student::create(['document' => '555', 'first_name' => 'Luis', 'last_name' => 'Rojas', 'email' => 'luis@example.com']);

        Livewire::actingAs($this->admin)->test(Boletines::class)
            ->set('studentId', (string) $ajeno->id)
            ->assertViewHas('boletin', null);
    });
});

describe('qué notas lleva el boletín', function () {
    beforeEach(function () {
        // Humanidades: Inglés y Lengua. A Inglés le falta la nota del M2, como
        // en el boletín que la coordinación no entendía.
        $area = Area::create(['cycle_id' => $this->cycle->id, 'name' => 'Humanidades', 'code' => 'HUM', 'display_order' => 1]);

        foreach (['ING' => ['Inglés', 4.5, null], 'LEN' => ['Lengua Castellana', 4.16, 3.2]] as $code => [$name, $m1, $m2]) {
            $subject = Subject::create(['code' => $code, 'name' => $name]);
            $area->subjects()->attach($subject);

            foreach ([1 => $m1, 2 => $m2] as $period => $score) {
                $course = Course::create(['code' => "C5{$code}M{$period}", 'cycle_id' => $this->cycle->id, 'subject_id' => $subject->id, 'period' => $period]);

                if ($score !== null) {
                    Grade::create(['course_id' => $course->id, 'student_id' => $this->student->id, 'score' => $score]);
                }
            }
        }

        $this->boletin = fn (?string $span) => app(BoletinService::class)->forSpan($this->student, $this->cycle->fresh(), $span);
    });

    it('cada materia del área lleva sus propias notas en las mismas columnas', function () {
        $humanidades = collect(($this->boletin)('semestre')['lines'])->firstWhere('name', 'Humanidades');

        expect($humanidades)->toMatchArray(['type' => 'area', 'p1' => 4.33, 'p2' => 3.2, 'final' => 4.09])
            ->and($humanidades['components'])->toHaveCount(2)
            ->and($humanidades['components'][0])->toMatchArray(['name' => 'Inglés', 'p1' => 4.5, 'p2' => null, 'final' => 4.5])
            ->and($humanidades['components'][1])->toMatchArray(['name' => 'Lengua Castellana', 'p1' => 4.16, 'p2' => 3.2, 'final' => 3.68]);
    });

    it('el boletín de un módulo trae solo las notas de ese módulo', function () {
        $m1 = ($this->boletin)('m1');
        $m2 = ($this->boletin)('m2');
        $lines = fn (array $boletin) => collect($boletin['lines'])->keyBy('name');

        expect($m1)->toMatchArray(['period' => 1, 'span' => 'm1', 'detail_columns' => [], 'final_label' => 'Nota'])
            ->and($lines($m1)['Matematicas']['final'])->toBe(4.0)
            ->and($lines($m1)['Humanidades']['final'])->toBe(4.33)
            ->and($m1['overall'])->toBe(4.17)
            ->and($lines($m2)['Matematicas']['final'])->toBe(3.0)
            // En el M2 Inglés no tiene nota: Humanidades es solo Lengua.
            ->and($lines($m2)['Humanidades']['final'])->toBe(3.2)
            ->and($lines($m2)['Humanidades']['components'][0])->toMatchArray(['name' => 'Inglés', 'final' => null]);
    });

    it('el boletín de un módulo es definitivo cuando ese módulo se cierra', function () {
        expect(($this->boletin)('m1')['partial'])->toBeTrue();

        $this->cycle->closeModule(1);

        expect(($this->boletin)('m1')['partial'])->toBeFalse()
            ->and(($this->boletin)('m2')['partial'])->toBeTrue()
            ->and(($this->boletin)('semestre')['partial'])->toBeTrue();
    });

    it('un ciclo de un semestre no tiene año: se queda en el semestre', function () {
        expect(BoletinService::spans($this->cycle))->toBe(['m1' => 'Módulo 1', 'm2' => 'Módulo 2', 'semestre' => 'Semestre'])
            ->and(($this->boletin)('anio')['span'])->toBe('semestre')
            ->and(($this->boletin)('cualquiera')['span'])->toBe('semestre');
    });

    it('cuenta las materias perdidas una por una, como el certificado', function () {
        Grade::whereHas('course', fn ($query) => $query->where('code', 'like', 'C5ING%'))->update(['score' => 2.0]);

        $boletin = ($this->boletin)('semestre');

        // Humanidades da 2.84, pero lo perdido es Inglés: una sola materia.
        expect(collect($boletin['lines'])->firstWhere('name', 'Humanidades')['final'])->toBe(2.84)
            ->and(BoletinService::failedSubjects($boletin['lines']))->toBe(1);
    });

    it('la pantalla cambia de lectura y descarga la misma', function () {
        Livewire::actingAs($this->admin)
            ->test(Boletines::class)
            ->set('studentId', (string) $this->student->id)
            ->assertSee('Promedio de Inglés y Lengua Castellana')
            ->assertSee('Mód. 1')
            ->set('span', 'm1')
            ->assertSet('span', 'm1')
            ->assertViewHas('downloadParams', ['notas' => 'm1'])
            ->assertSee('Módulo 1 sigue abierto')
            ->assertSee(route('admin.boletines.download-student', ['cycle' => $this->cycle->id, 'student' => $this->student->id, 'notas' => 'm1']), false)
            ->assertDontSee('Mód. 1');
    });

    it('el PDF y el ZIP de un módulo dicen cuál es', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.boletines.download-student', ['cycle' => $this->cycle->id, 'student' => $this->student->id, 'notas' => 'm2']))
            ->assertOk()
            ->assertDownload('boletin-arevalo-hernandez-lineth-yuliana-1118544017-modulo-2.pdf');

        $this->actingAs($this->admin)
            ->get(route('admin.boletines.download', ['cycle' => $this->cycle->id, 'notas' => 'm1']))
            ->assertOk()
            ->assertDownload('boletines-ciclo-5-modulo-1.zip');
    });
});
