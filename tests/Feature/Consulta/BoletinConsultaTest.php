<?php

use App\Livewire\Consulta\Boletin;
use App\Livewire\Consulta\Lookup;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use Livewire\Livewire;

beforeEach(function () {
    $this->cycle = Cycle::create([
        'code' => 'C5-2026-1',
        'level' => '5',
        'semester' => 1,
        'year' => 2026,
        'name' => 'Ciclo 5',
        'closed_at' => now(),
    ]);

    $this->group = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 5-1', 'code' => 'G1']);

    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematicas']);

    $first = Course::create([
        'code' => 'C5MATM1',
        'cycle_id' => $this->cycle->id,
        'subject_id' => $subject->id,
        'period' => 1,
    ]);

    $second = Course::create([
        'code' => 'C5MATM2',
        'cycle_id' => $this->cycle->id,
        'subject_id' => $subject->id,
        'period' => 2,
    ]);

    $this->student = Student::create([
        'document' => '1118544017',
        'first_name' => 'LINETH YULIANA',
        'last_name' => 'AREVALO HERNANDEZ',
        'email' => 'lineth@example.com',
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'cycle_id' => $this->cycle->id,
        'group_id' => $this->group->id,
    ]);

    Grade::create(['course_id' => $first->id, 'student_id' => $this->student->id, 'score' => 4.0]);
    Grade::create(['course_id' => $second->id, 'student_id' => $this->student->id, 'score' => 3.0]);

    // Un semestre y una estudiante ajenos: son los que nunca debería alcanzar.
    $this->otherCycle = Cycle::create([
        'code' => 'C6-2026-1',
        'level' => '6',
        'semester' => 1,
        'year' => 2026,
        'name' => 'Ciclo 6',
    ]);

    $this->otherStudent = Student::create([
        'document' => '52463408',
        'first_name' => 'SONIA ROCIO',
        'last_name' => 'SALAZAR HERRERA',
        'email' => 'sonia@example.com',
    ]);

    Enrollment::create(['student_id' => $this->otherStudent->id, 'cycle_id' => $this->otherCycle->id]);
});

it('abre la consulta a cualquiera, sin autenticación', function () {
    $this->get(route('consulta.lookup'))->assertOk();
});

it('entrega el boletín con el documento y el apellido', function () {
    Livewire::test(Lookup::class)
        ->set('document', '1118544017')
        ->set('lastName', 'Arévalo')
        ->call('consultar')
        ->assertHasNoErrors()
        ->assertRedirect(route('consulta.boletin'));

    expect(session(Boletin::SESSION_KEY))->toBe($this->student->id);
});

it('acepta el documento con puntos y el segundo apellido', function () {
    Livewire::test(Lookup::class)
        ->set('document', '1.118.544.017')
        ->set('lastName', 'hernandez')
        ->call('consultar')
        ->assertHasNoErrors();

    expect(session(Boletin::SESSION_KEY))->toBe($this->student->id);
});

it('no abre nada cuando el apellido no corresponde al documento', function () {
    Livewire::test(Lookup::class)
        ->set('document', '1118544017')
        ->set('lastName', 'SALAZAR')
        ->call('consultar')
        ->assertHasErrors('document');

    expect(session(Boletin::SESSION_KEY))->toBeNull();
});

it('no acepta un apellido incompleto', function () {
    Livewire::test(Lookup::class)
        ->set('document', '1118544017')
        ->set('lastName', 'Areva')
        ->call('consultar')
        ->assertHasErrors('document');

    expect(session(Boletin::SESSION_KEY))->toBeNull();
});

it('no dice nada de un documento que no existe', function () {
    Livewire::test(Lookup::class)
        ->set('document', '9999999999')
        ->set('lastName', 'AREVALO')
        ->call('consultar')
        ->assertHasErrors('document');

    expect(session(Boletin::SESSION_KEY))->toBeNull();
});

it('cierra la consulta después de varios intentos fallidos', function () {
    foreach (range(1, 8) as $ignored) {
        Livewire::test(Lookup::class)
            ->set('document', '1118544017')
            ->set('lastName', 'SALAZAR')
            ->call('consultar');
    }

    // El noveno intento es bueno, pero ya no hay más oportunidades.
    Livewire::test(Lookup::class)
        ->set('document', '1118544017')
        ->set('lastName', 'AREVALO')
        ->call('consultar')
        ->assertHasErrors('document');

    expect(session(Boletin::SESSION_KEY))->toBeNull();
});

it('manda a la pantalla de consulta cuando no hay sesión', function () {
    $this->get(route('consulta.boletin'))->assertRedirect(route('consulta.lookup'));
});

it('muestra las notas del semestre del estudiante', function () {
    session([Boletin::SESSION_KEY => $this->student->id]);

    Livewire::test(Boletin::class)
        ->assertSet('cycleId', $this->cycle->id)
        ->assertSee('Matematicas')
        ->assertSee('3.50')
        ->assertDontSee('Ciclo 6');
});

it('arma la página completa del boletín', function () {
    session([Boletin::SESSION_KEY => $this->student->id]);

    $this->get(route('consulta.boletin'))
        ->assertOk()
        ->assertSee('Consulta de boletines')
        ->assertSee('Descargar PDF')
        ->assertSee('LINETH YULIANA AREVALO HERNANDEZ');
});

it('avisa que el semestre sigue en curso', function () {
    $this->cycle->update(['closed_at' => null]);

    session([Boletin::SESSION_KEY => $this->student->id]);

    Livewire::test(Boletin::class)->assertSee('Semestre en curso');
});

it('esconde el semestre histórico y ofrece el boletín de los dos semestres', function () {
    $previous = Cycle::create([
        'code' => 'C5-2025-2',
        'level' => '5',
        'semester' => 2,
        'year' => 2025,
        'name' => 'Ciclo 5 del año pasado',
    ]);

    Enrollment::create(['student_id' => $this->student->id, 'cycle_id' => $previous->id]);
    $this->cycle->update(['previous_cycle_id' => $previous->id]);

    session([Boletin::SESSION_KEY => $this->student->id]);

    Livewire::test(Boletin::class)
        ->assertDontSee('Ciclo 5 del año pasado')
        ->assertSee('Incluir el semestre anterior');
});

it('ignora un semestre en el que el estudiante no está matriculado', function () {
    session([Boletin::SESSION_KEY => $this->student->id]);

    Livewire::test(Boletin::class)
        ->call('selectCycle', $this->otherCycle->id)
        ->assertSet('cycleId', $this->cycle->id);
});

it('cierra la sesión al salir', function () {
    session([Boletin::SESSION_KEY => $this->student->id]);

    Livewire::test(Boletin::class)
        ->call('salir')
        ->assertRedirect(route('consulta.lookup'));

    expect(session(Boletin::SESSION_KEY))->toBeNull();
});

it('descarga el PDF del estudiante que hizo la consulta', function () {
    session([Boletin::SESSION_KEY => $this->student->id]);

    $response = $this->get(route('consulta.boletin.download', $this->cycle));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('no entrega ningún PDF sin consulta previa', function () {
    $this->get(route('consulta.boletin.download', $this->cycle))->assertForbidden();
});

it('no entrega el PDF de un semestre ajeno', function () {
    session([Boletin::SESSION_KEY => $this->student->id]);

    $this->get(route('consulta.boletin.download', $this->otherCycle))->assertNotFound();
});

it('le explica al estudiante por qué no encontró su boletín', function () {
    Livewire::test(Lookup::class)
        ->set('document', '1118544017')
        ->set('lastName', 'SALAZAR')
        ->call('consultar')
        ->assertSee('acércate a secretaría');
});
