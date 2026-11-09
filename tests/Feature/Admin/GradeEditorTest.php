<?php

use App\Livewire\Admin\CourseGrades;
use App\Livewire\Admin\GradeEditor;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('docente');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    // El semestre anterior: es al que no se debería entrar por defecto.
    $this->previous = Cycle::create(['code' => 'Ciclo4AS12026', 'level' => '4A', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 4A']);

    // El semestre en curso, ya cerrado para los docentes.
    $this->cycle = Cycle::create(['code' => 'Ciclo5S22026', 'level' => '5', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 5']);
    $this->cycle->close();
    $this->groupOne = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 5-1']);
    $this->groupTwo = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 5-2']);

    $this->teacher = Teacher::create(['username' => 'jperez', 'first_name' => 'Juan', 'last_name' => 'Pérez']);

    $math = Subject::create(['code' => 'MAT', 'name' => 'Matemáticas']);
    $language = Subject::create(['code' => 'LEN', 'name' => 'Lengua Castellana']);

    $this->mathOne = Course::create(['code' => 'C51MATM1S22026', 'cycle_id' => $this->cycle->id, 'subject_id' => $math->id, 'group_id' => $this->groupOne->id, 'teacher_id' => $this->teacher->id, 'period' => 1]);
    $this->mathTwo = Course::create(['code' => 'C51MATM2S22026', 'cycle_id' => $this->cycle->id, 'subject_id' => $math->id, 'group_id' => $this->groupOne->id, 'teacher_id' => $this->teacher->id, 'period' => 2]);
    $this->language = Course::create(['code' => 'C5LENM2S22026', 'cycle_id' => $this->cycle->id, 'subject_id' => $language->id, 'group_id' => null, 'period' => 2]);

    $this->students = collect([
        ['111', 'Ana', 'Gomez', $this->groupOne],
        ['222', 'Luis', 'Rojas', $this->groupOne],
        ['333', 'Sara', 'Diaz', $this->groupTwo],
    ])->map(function (array $row) {
        [$document, $first, $last, $group] = $row;

        $student = Student::create(['document' => $document, 'first_name' => $first, 'last_name' => $last, 'email' => "{$document}@example.com", 'username' => "u{$document}"]);
        Enrollment::create(['student_id' => $student->id, 'cycle_id' => $this->cycle->id, 'group_id' => $group->id]);

        return $student;
    });

    // Matemáticas del módulo 1 queda completa; el resto, sin notas.
    foreach ($this->students->take(2) as $student) {
        Grade::create(['course_id' => $this->mathOne->id, 'student_id' => $student->id, 'score' => 4.0]);
    }
});

/**
 * Los cursos que quedaron a la vista, por código.
 *
 * @return list<string>
 */
function visibleCodes(Testable $component): array
{
    return $component->viewData('byModule')->flatten(1)->pluck('code')->sort()->values()->all();
}

describe('editor de notas', function () {
    test('no es para cualquiera', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.grade-editor'))
            ->assertForbidden();
    });

    test('abre en el semestre más reciente, con su primer ciclo elegido', function () {
        Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->assertSet('year', '2026')
            ->assertSet('semester', '2')
            ->assertSet('cycleId', (string) $this->cycle->id)
            ->assertSee('Matemáticas');
    });

    test('cambiar de semestre cambia el ciclo', function () {
        Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->set('semester', '1')
            ->assertSet('cycleId', (string) $this->previous->id);
    });

    test('un enlace con el ciclo manda sobre el año y el semestre', function () {
        Livewire::withQueryParams(['ciclo' => $this->previous->id])
            ->actingAs($this->admin)
            ->test(GradeEditor::class)
            ->assertSet('semester', '1')
            ->assertSet('cycleId', (string) $this->previous->id);
    });

    test('filtra por módulo', function () {
        $component = Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->set('period', '2');

        expect(visibleCodes($component))->toBe(['C51MATM2S22026', 'C5LENM2S22026']);
    });

    test('el grupo deja también los cursos de todo el ciclo', function () {
        $component = Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->set('groupId', (string) $this->groupTwo->id);

        expect(visibleCodes($component))->toBe(['C5LENM2S22026']);
    });

    test('busca por docente sin importar las tildes', function () {
        $component = Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->set('search', 'perez');

        expect(visibleCodes($component))->toBe(['C51MATM1S22026', 'C51MATM2S22026']);
    });

    test('separa lo que tiene notas pendientes de lo completo', function () {
        $component = Livewire::actingAs($this->admin)->test(GradeEditor::class);

        expect($component->viewData('counts'))->toBe(['all' => 3, 'pending' => 2, 'complete' => 1]);

        $component->set('status', 'pendientes');
        expect(visibleCodes($component))->toBe(['C51MATM2S22026', 'C5LENM2S22026']);

        $component->set('status', 'completas');
        expect(visibleCodes($component))->toBe(['C51MATM1S22026']);
    });

    test('cuenta lo esperado según el grupo de cada curso', function () {
        $cards = Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->viewData('byModule')
            ->flatten(1)
            ->keyBy('code');

        // Matemáticas es del grupo 5-1 (2 estudiantes); Lengua, de todo el ciclo (3).
        expect($cards['C51MATM1S22026']['students'])->toBe(2)
            ->and($cards['C51MATM1S22026']['status'])->toBe('completo')
            ->and($cards['C5LENM2S22026']['students'])->toBe(3)
            ->and($cards['C5LENM2S22026']['teacher_open'])->toBeFalse();
    });

    test('limpiar filtros deja todo a la vista', function () {
        Livewire::actingAs($this->admin)
            ->test(GradeEditor::class)
            ->set('period', '2')
            ->set('search', 'nada')
            ->call('clearFilters')
            ->assertSet('period', '')
            ->assertSet('search', '');
    });
});

describe('planilla del panel', function () {
    test('la coordinación abre cualquier curso y ve quién lo dicta', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.grade-editor.course', $this->mathTwo))
            ->assertOk()
            ->assertSee('Juan Pérez')
            ->assertSee('Estás corrigiendo como coordinación');
    });

    test('un docente no entra a la planilla del panel', function () {
        $user = User::factory()->create();
        $user->assignRole('docente');
        $this->teacher->user()->associate($user)->save();

        $this->actingAs($user)
            ->get(route('admin.grade-editor.course', $this->mathTwo))
            ->assertForbidden();
    });

    test('la coordinación escribe aunque el semestre esté cerrado', function () {
        $student = $this->students->first();

        Livewire::actingAs($this->admin)
            ->test(CourseGrades::class, ['course' => $this->mathTwo])
            ->assertSet('editable', true)
            ->set("scores.{$student->id}", '4,5')
            ->assertHasNoErrors();

        expect(Grade::where('course_id', $this->mathTwo->id)->where('student_id', $student->id)->value('score'))->toEqual('4.50');
    });

    test('quien pierde el rol deja de poder escribir', function () {
        $student = $this->students->first();

        $component = Livewire::actingAs($this->admin)->test(CourseGrades::class, ['course' => $this->mathTwo]);

        $this->admin->removeRole('admin');

        $component->set("scores.{$student->id}", '5')->assertStatus(403);

        expect(Grade::where('course_id', $this->mathTwo->id)->exists())->toBeFalse();
    });

    test('solo deja calificar a los estudiantes del grupo del curso', function () {
        $outsider = $this->students->last();

        Livewire::actingAs($this->admin)
            ->test(CourseGrades::class, ['course' => $this->mathTwo])
            ->set("scores.{$outsider->id}", '5')
            ->assertStatus(403);
    });
});
