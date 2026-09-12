<?php

use App\Livewire\Teacher\Gradebook;
use App\Livewire\Teacher\MyCourses;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('docente');

    $this->cycle = Cycle::create([
        'code' => 'C1-2026-1',
        'level' => 'Ciclo 1',
        'semester' => 1,
        'year' => 2026,
        'name' => 'Ciclo 1 2026-1',
    ]);

    $this->group = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Grupo A', 'code' => 'GA']);

    $this->user = User::factory()->create();
    $this->user->assignRole('docente');

    $this->teacher = Teacher::create([
        'username' => 'jperez',
        'first_name' => 'Juan',
        'last_name' => 'Perez',
        'email' => 'jperez@iete.edu.co',
    ]);
    $this->teacher->user()->associate($this->user)->save();

    $this->course = Course::create([
        'code' => 'MAT-1',
        'cycle_id' => $this->cycle->id,
        'subject_id' => Subject::create(['code' => 'MAT', 'name' => 'Matematicas'])->id,
        'group_id' => $this->group->id,
        'teacher_id' => $this->teacher->id,
        'period' => 1,
    ]);

    // Una materia de otro docente: es la que nunca debería ver ni tocar.
    $other = Teacher::create(['username' => 'mlopez', 'first_name' => 'Maria', 'last_name' => 'Lopez']);

    $this->otherCourse = Course::create([
        'code' => 'LEN-1',
        'cycle_id' => $this->cycle->id,
        'subject_id' => Subject::create(['code' => 'LEN', 'name' => 'Lenguaje'])->id,
        'group_id' => $this->group->id,
        'teacher_id' => $other->id,
        'period' => 1,
    ]);

    $this->student = Student::create([
        'document' => '111',
        'first_name' => 'Ana',
        'last_name' => 'Gomez',
        'email' => 'ana@example.com',
        'username' => 'ana',
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'cycle_id' => $this->cycle->id,
        'group_id' => $this->group->id,
    ]);
});

describe('acceso al portal', function () {
    test('un invitado va al login', function () {
        $this->get(route('teacher.courses'))->assertRedirect(route('login'));
    });

    test('una cuenta sin docente vinculado no entra', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('teacher.courses'))
            ->assertForbidden();
    });

    test('el docente entra a su portal', function () {
        $this->actingAs($this->user)
            ->get(route('teacher.courses'))
            ->assertOk();
    });

    test('el tablero lleva al docente a sus materias', function () {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertRedirect(route('teacher.courses'));
    });

    test('a un administrador el tablero no lo desvía', function () {
        Role::findOrCreate('admin');
        $this->user->assignRole('admin');

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk();
    });
});

describe('mis materias', function () {
    test('solo lista las materias propias', function () {
        Livewire::actingAs($this->user)
            ->test(MyCourses::class)
            ->assertSee('Matematicas')
            ->assertDontSee('Lenguaje');
    });

    test('cuenta los estudiantes y las notas que faltan', function () {
        Livewire::actingAs($this->user)
            ->test(MyCourses::class)
            ->assertViewHas('summary', fn (array $summary) => $summary['courses'] === 1
                && $summary['students'] === 1
                && $summary['pending'] === 1);
    });

    test('abre en el semestre en curso y no mezcla el anterior', function () {
        $anterior = Cycle::create([
            'code' => 'C1-2025-2',
            'level' => 'Ciclo 1',
            'semester' => 2,
            'year' => 2025,
            'name' => 'Ciclo 1',
        ]);

        Course::create([
            'code' => 'MAT-VIEJO',
            'cycle_id' => $anterior->id,
            'subject_id' => Subject::create(['code' => 'FIS', 'name' => 'Fisica'])->id,
            'teacher_id' => $this->teacher->id,
            'period' => 1,
        ]);

        Livewire::actingAs($this->user)
            ->test(MyCourses::class)
            ->assertSet('cycleId', (string) $this->cycle->id)
            ->assertSee('Matematicas')
            ->assertDontSee('Fisica')
            ->set('cycleId', '')
            ->assertSee('Fisica');
    });
});

describe('planilla de notas', function () {
    test('el docente abre la planilla de su materia', function () {
        $this->actingAs($this->user)
            ->get(route('teacher.gradebook', $this->course))
            ->assertOk()
            ->assertSee('Gomez');
    });

    test('el docente no abre la planilla de otro docente', function () {
        $this->actingAs($this->user)
            ->get(route('teacher.gradebook', $this->otherCourse))
            ->assertForbidden();
    });

    test('salir de la casilla guarda la nota', function () {
        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set("scores.{$this->student->id}", '4.25')
            ->assertHasNoErrors();

        expect(Grade::where('course_id', $this->course->id)->value('score'))->toEqual('4.25');
    });

    test('vaciar la casilla borra la nota', function () {
        Grade::create(['course_id' => $this->course->id, 'student_id' => $this->student->id, 'score' => 3.5]);

        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set("scores.{$this->student->id}", '')
            ->assertHasNoErrors();

        expect(Grade::where('course_id', $this->course->id)->value('score'))->toBeNull();
    });

    test('una nota fuera de 0 a 5 se rechaza', function () {
        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set("scores.{$this->student->id}", '7')
            ->assertHasErrors("scores.{$this->student->id}");

        expect(Grade::where('course_id', $this->course->id)->exists())->toBeFalse();
    });

    test('guardar todo escribe la planilla completa', function () {
        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set('scores', [$this->student->id => '3.80'])
            ->call('saveAll')
            ->assertHasNoErrors();

        expect(Grade::where('course_id', $this->course->id)->value('score'))->toEqual('3.80');
    });

    test('no se puede calificar a alguien que no está en el curso', function () {
        $outsider = Student::create([
            'document' => '222',
            'first_name' => 'Luis',
            'last_name' => 'Rojas',
            'email' => 'luis@example.com',
            'username' => 'luis',
        ]);

        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set("scores.{$outsider->id}", '5')
            ->assertStatus(403);

        expect(Grade::where('student_id', $outsider->id)->exists())->toBeFalse();
    });

    test('el filtro de pendientes deja solo a quien le falta nota', function () {
        $graded = Student::create([
            'document' => '333',
            'first_name' => 'Sara',
            'last_name' => 'Diaz',
            'email' => 'sara@example.com',
            'username' => 'sara',
        ]);

        Enrollment::create([
            'student_id' => $graded->id,
            'cycle_id' => $this->cycle->id,
            'group_id' => $this->group->id,
        ]);

        Grade::create(['course_id' => $this->course->id, 'student_id' => $graded->id, 'score' => 4.0]);

        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set('onlyPending', true)
            ->assertSee('Gomez')
            ->assertDontSee('Diaz');
    });
});
