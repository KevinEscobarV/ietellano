<?php

use App\Livewire\Dashboard;
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

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

describe('tablero del semestre', function () {
    beforeEach(function () {
        $this->cycle = Cycle::create([
            'code' => 'C5-2026-2',
            'level' => '5',
            'semester' => 2,
            'year' => 2026,
            'name' => 'Ciclo 5',
        ]);

        $this->group = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 5-1', 'code' => 'G1']);

        $this->teacher = Teacher::create([
            'username' => 'jperez',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'jperez@iete.edu.co',
        ]);

        $this->course = Course::create([
            'code' => 'C51MATM1S22026',
            'cycle_id' => $this->cycle->id,
            'subject_id' => Subject::create(['code' => 'MAT', 'name' => 'Matematicas'])->id,
            'group_id' => $this->group->id,
            'teacher_id' => $this->teacher->id,
            'period' => 1,
        ]);

        foreach (['ana', 'beto'] as $name) {
            $student = Student::create([
                'document' => $name,
                'first_name' => $name,
                'last_name' => 'Gomez',
                'email' => "{$name}@example.com",
            ]);

            Enrollment::create([
                'student_id' => $student->id,
                'cycle_id' => $this->cycle->id,
                'group_id' => $this->group->id,
            ]);

            $this->{$name} = $student;
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('admin')->name);
    });

    test('cuenta la matrícula y el avance de notas del semestre', function () {
        Grade::create(['course_id' => $this->course->id, 'student_id' => $this->ana->id, 'score' => 4.5]);

        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->assertSet('term', '2026-2')
            ->assertViewHas('enrollment', fn (array $e) => $e['total'] === 2)
            // Dos matriculados, una nota puesta: falta la mitad.
            ->assertViewHas('progress', fn (array $p) => $p['done'] === 1 && $p['expected'] === 2 && $p['percent'] === 50)
            ->assertViewHas('behind', fn (array $b) => $b[0]['missing'] === 1 && $b[0]['teacher'] === 'Juan Perez');
    });

    test('avisa de los docentes que todavía no pueden entrar', function () {
        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->assertSee('Un docente sin cuenta de acceso');

        $this->teacher->user()->associate(User::factory()->create())->save();

        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->assertSee('Todos los docentes tienen acceso');
    });

    test('marca a quien no alcanza a aprobar', function () {
        Grade::create(['course_id' => $this->course->id, 'student_id' => $this->ana->id, 'score' => 2.0]);
        Grade::create(['course_id' => $this->course->id, 'student_id' => $this->beto->id, 'score' => 4.0]);

        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->assertViewHas('atRisk', fn (array $r) => $r['total'] === 1 && $r['students'][0]['average'] === 2.0)
            ->assertViewHas('performance', fn (array $p) => $p['total'] === 2 && $p['average'] === 3.0);
    });

    test('deja mirar un semestre anterior', function () {
        Cycle::create([
            'code' => 'C5-2026-1',
            'level' => '5',
            'semester' => 1,
            'year' => 2026,
            'name' => 'Ciclo 5',
        ]);

        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->assertSet('term', '2026-2')
            ->set('term', '2026-1')
            ->assertViewHas('enrollment', fn (array $e) => $e['total'] === 0);
    });

    test('al docente sin cargo administrativo lo manda a sus materias', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('docente')->name);
        $this->teacher->user()->associate($user)->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('teacher.courses'));
    });
});
