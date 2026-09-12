<?php

use App\Livewire\Admin\Boletines;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
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
