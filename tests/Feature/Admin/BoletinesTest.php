<?php

use App\Livewire\Admin\Boletines;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
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
