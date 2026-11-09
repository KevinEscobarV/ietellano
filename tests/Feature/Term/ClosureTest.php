<?php

use App\Enums\AttendanceStatus;
use App\Livewire\Admin\GradeEditor;
use App\Livewire\Admin\Teachers;
use App\Livewire\Admin\TermClosure;
use App\Livewire\Settings\DeleteUserForm;
use App\Livewire\Settings\Profile;
use App\Livewire\Teacher\Attendance as TeacherAttendance;
use App\Livewire\Teacher\Gradebook;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\EditWindow;
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
    Role::findOrCreate('admin');

    $this->cycle = Cycle::create([
        'code' => 'C5-2026-1',
        'level' => '5',
        'semester' => 1,
        'year' => 2026,
        'name' => 'Ciclo 5',
        'starts_on' => '2026-02-07',
        'ends_on' => '2026-02-28',
        'class_weekday' => 6,
    ]);

    $this->group = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 5', 'code' => 'G1']);

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
        'code' => 'C5MATM1S12026',
        'cycle_id' => $this->cycle->id,
        'subject_id' => Subject::create(['code' => 'MAT', 'name' => 'Matematicas'])->id,
        'group_id' => $this->group->id,
        'teacher_id' => $this->teacher->id,
        'period' => 1,
    ]);

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

    $this->session = ClassSession::create(['cycle_id' => $this->cycle->id, 'date' => '2026-02-07']);

    $this->close = fn () => $this->cycle->close();

    $this->putScore = fn () => Livewire::actingAs($this->user)
        ->test(Gradebook::class, ['course' => $this->course])
        ->set("scores.{$this->student->id}", '4.5');

    $this->markAbsent = fn () => Livewire::actingAs($this->user)
        ->test(TeacherAttendance::class, ['course' => $this->course])
        ->set('sessionId', (string) $this->session->id)
        ->set("status.{$this->student->id}", AttendanceStatus::Absent->value);
});

describe('semestre cerrado', function () {
    it('deja escribir mientras el semestre sigue abierto', function () {
        ($this->putScore)();
        ($this->markAbsent)();

        expect((float) Grade::where('student_id', $this->student->id)->value('score'))->toBe(4.5)
            ->and(Attendance::count())->toBe(1);
    });

    it('el docente sigue viendo sus planillas después del cierre', function () {
        ($this->close)();

        $this->actingAs($this->user)->get(route('teacher.gradebook', $this->course))->assertOk();
        $this->actingAs($this->user)->get(route('teacher.attendance', $this->course))->assertOk();
    });

    it('pero ya no puede cambiar una nota', function () {
        ($this->close)();

        ($this->putScore)()->assertForbidden();

        expect(Grade::count())->toBe(0);
    });

    it('ni pasar asistencia', function () {
        ($this->close)();

        ($this->markAbsent)()->assertForbidden();

        Livewire::actingAs($this->user)
            ->test(TeacherAttendance::class, ['course' => $this->course])
            ->set('sessionId', (string) $this->session->id)
            ->call('markAllPresent')
            ->assertForbidden();

        expect(Attendance::count())->toBe(0);
    });

    it('deja la planilla en solo lectura, sin botón de guardar', function () {
        ($this->close)();

        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->assertSee('El Módulo 1 está cerrado')
            ->assertDontSee('Guardar todo');
    });

    it('no se cierra de un clic: primero pregunta', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $panel = Livewire::actingAs($admin)->test(TermClosure::class)
            ->call('confirmModule', 'close', 1, $this->cycle->id)
            ->assertSet('showConfirmModal', true)
            // El aviso dice sobre qué módulo y qué ciclo es, que un confirm del navegador no podía.
            ->assertSee('Módulo 1 · Ciclo 5');

        expect($this->cycle->refresh()->isModuleClosed(1))->toBeFalse();

        $panel->call('confirmed');

        expect($this->cycle->refresh()->isModuleClosed(1))->toBeTrue();

        ($this->putScore)()->assertForbidden();
    });

    it('un día sin asistencia no se lee como si todos hubieran ido', function () {
        ($this->close)();

        Livewire::actingAs($this->user)
            ->test(TeacherAttendance::class, ['course' => $this->course])
            ->set('sessionId', (string) $this->session->id)
            ->assertSee('Sin registrar')
            ->assertDontSee('Todos presentes');
    });

    it('el administrador puede volver a abrirlo', function () {
        ($this->close)();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(TermClosure::class)->call('reopenModule', 1, $this->cycle->id);

        expect($this->cycle->refresh()->isModuleClosed(1))->toBeFalse()
            ->and($this->cycle->isClosed())->toBeFalse();

        ($this->putScore)();

        expect(Grade::count())->toBe(1);
    });
});

describe('cierre por módulo', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // La misma materia en el Módulo 2, con el mismo docente.
        $this->courseTwo = Course::create([
            'code' => 'C5MATM2S12026',
            'cycle_id' => $this->cycle->id,
            'subject_id' => $this->course->subject_id,
            'group_id' => $this->group->id,
            'teacher_id' => $this->teacher->id,
            'period' => 2,
        ]);

        $this->putScoreTwo = fn () => Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->courseTwo])
            ->set("scores.{$this->student->id}", '3.5');
    });

    it('cerrar el Módulo 1 deja el Módulo 2 abierto', function () {
        $this->cycle->closeModule(1);

        ($this->putScore)()->assertForbidden();
        ($this->putScoreTwo)()->assertHasNoErrors();

        expect(Grade::where('course_id', $this->courseTwo->id)->exists())->toBeTrue()
            ->and(Grade::where('course_id', $this->course->id)->exists())->toBeFalse();
    });

    it('el semestre queda cerrado solo cuando lo están los dos módulos', function () {
        $this->cycle->closeModule(1);

        expect($this->cycle->isClosed())->toBeFalse();

        $this->cycle->closeModule(2);

        expect($this->cycle->isClosed())->toBeTrue()
            ->and($this->cycle->closedAt())->not->toBeNull();
    });

    it('volver a cerrar un módulo cerrado conserva su fecha', function () {
        $this->travelTo(now()->subWeek());
        $this->cycle->closeModule(1);
        $this->travelBack();

        $closedOn = $this->cycle->closureFor(1)->closes_at;

        $this->cycle->close();

        expect($this->cycle->closureFor(1)->closes_at->equalTo($closedOn))->toBeTrue();
    });

    it('un cierre programado deja escribir hasta la hora y se cumple solo', function () {
        $closesAt = now()->addDay()->setTime(18, 0);
        $this->cycle->scheduleModuleClosure(1, $closesAt);

        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->assertSee('El Módulo 1 se cierra el')
            ->set("scores.{$this->student->id}", '4.5')
            ->assertHasNoErrors();

        $this->travelTo($closesAt->copy()->addMinute());

        ($this->putScore)()->assertForbidden();

        expect((float) Grade::where('course_id', $this->course->id)->value('score'))->toBe(4.5);
    });

    it('cerrar ahora vence las ventanas de ese módulo, no las del otro', function () {
        $deUno = EditWindow::create(['cycle_id' => $this->cycle->id, 'course_id' => $this->course->id, 'period' => 1, 'closes_at' => now()->addWeek()]);
        $moduloUno = EditWindow::create(['cycle_id' => $this->cycle->id, 'period' => 1, 'closes_at' => now()->addWeek()]);
        $deDos = EditWindow::create(['cycle_id' => $this->cycle->id, 'course_id' => $this->courseTwo->id, 'period' => 2, 'closes_at' => now()->addWeek()]);

        $this->cycle->closeModule(1);

        expect($deUno->refresh()->isActive())->toBeFalse()
            ->and($moduloUno->refresh()->isActive())->toBeFalse()
            ->and($deDos->refresh()->isActive())->toBeTrue();

        ($this->putScore)()->assertForbidden();
    });

    it('una ventana de módulo abre ese módulo y no el otro', function () {
        $this->cycle->close();

        EditWindow::create(['cycle_id' => $this->cycle->id, 'period' => 2, 'closes_at' => now()->addDay()]);

        ($this->putScore)()->assertForbidden();
        ($this->putScoreTwo)()->assertHasNoErrors();
    });

    it('el panel cierra un módulo en todos los ciclos del semestre y en ningún otro', function () {
        $otroCiclo = Cycle::create(['code' => 'C6-2026-1', 'level' => '6', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 6']);
        $otroSemestre = Cycle::create(['code' => 'C5-2025-2', 'level' => '5', 'semester' => 2, 'year' => 2025, 'name' => 'Ciclo 5']);

        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->set('term', '2026-1')
            ->call('confirmModule', 'close', 1)
            ->assertSee('Módulo 1 · Ciclo 5, Ciclo 6')
            ->call('confirmed');

        expect($this->cycle->refresh()->isModuleClosed(1))->toBeTrue()
            ->and($otroCiclo->refresh()->isModuleClosed(1))->toBeTrue()
            ->and($this->cycle->isModuleClosed(2))->toBeFalse()
            ->and($otroSemestre->refresh()->isModuleClosed(1))->toBeFalse();
    });

    it('al cerrar avisa cuántas notas faltan', function () {
        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->call('confirmModule', 'close', 2)
            ->assertSee('Todavía falta 1 nota por poner.');
    });

    it('el panel programa el cierre y el docente ve la fecha', function () {
        $closesAt = now()->addDays(5)->setTime(18, 0);

        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->call('openScheduleForm', 2)
            ->set('scheduleAt', $closesAt->format('Y-m-d\TH:i'))
            ->call('scheduleClosure')
            ->assertHasNoErrors()
            ->assertSee('Se cierra el '.$closesAt->translatedFormat('j \d\e F'));

        expect($this->cycle->refresh()->closureFor(2)->closes_at->equalTo($closesAt))->toBeTrue()
            ->and($this->cycle->isModuleClosed(2))->toBeFalse();

        Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->courseTwo])
            ->assertSee('El Módulo 2 se cierra el');
    });

    it('programar no reabre un módulo que ya estaba cerrado', function () {
        $this->cycle->closeModule(1);

        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->call('openScheduleForm', 1)
            ->set('scheduleAt', now()->addDays(5)->format('Y-m-d\TH:i'))
            ->call('scheduleClosure');

        expect($this->cycle->refresh()->isModuleClosed(1))->toBeTrue();
    });

    it('no programa una fecha que ya pasó', function () {
        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->call('openScheduleForm', 1)
            ->set('scheduleAt', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('scheduleClosure')
            ->assertHasErrors('scheduleAt');

        expect($this->cycle->refresh()->closureFor(1))->toBeNull();
    });

    it('la lista deja a la vista las ventanas vigentes y pliega las vencidas', function () {
        EditWindow::create(['cycle_id' => $this->cycle->id, 'course_id' => $this->course->id, 'period' => 1, 'closes_at' => now()->addDay(), 'note' => 'Vigente']);
        EditWindow::create(['cycle_id' => $this->cycle->id, 'period' => 2, 'closes_at' => now()->subDay(), 'note' => 'Vencida']);

        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->assertViewHas('active', fn ($active) => $active->pluck('note')->all() === ['Vigente'])
            ->assertViewHas('expiredCount', 1)
            ->assertSee('1 ventana vencida')
            ->assertSee('Todo el Módulo 2');
    });

    it('no acepta un módulo que no existe', function () {
        Livewire::actingAs($this->admin)->test(TermClosure::class)
            ->call('confirmModule', 'close', 3)
            ->assertNotFound();
    });

    it('el editor de notas muestra cada módulo con su estado', function () {
        $this->cycle->closeModule(1);
        $this->cycle->scheduleModuleClosure(2, now()->addDays(3));

        Livewire::actingAs($this->admin)->test(GradeEditor::class)
            ->assertSee('M1 cerrado')
            ->assertSee('M2 hasta el');
    });
});

describe('ventanas de edición', function () {
    beforeEach(fn () => ($this->close)());

    /**
     * @param  array<string, mixed>  $attributes
     */
    function ventana(array $attributes): EditWindow
    {
        return EditWindow::create([...['closes_at' => now()->addDay()], ...$attributes]);
    }

    it('una ventana sobre la materia le devuelve la escritura', function () {
        ventana(['cycle_id' => $this->cycle->id, 'course_id' => $this->course->id]);

        ($this->putScore)();
        ($this->markAbsent)();

        expect(Grade::count())->toBe(1)
            ->and(Attendance::count())->toBe(1);
    });

    it('una ventana sin materia abre el semestre entero', function () {
        ventana(['cycle_id' => $this->cycle->id, 'course_id' => null]);

        ($this->putScore)();

        expect(Grade::count())->toBe(1);
    });

    it('una ventana ya vencida no sirve', function () {
        ventana([
            'cycle_id' => $this->cycle->id,
            'course_id' => $this->course->id,
            'closes_at' => now()->subMinute(),
        ]);

        ($this->putScore)()->assertForbidden();
    });

    it('la ventana de otra materia no abre la mía', function () {
        $otra = Course::create([
            'code' => 'C5LENM1S12026',
            'cycle_id' => $this->cycle->id,
            'subject_id' => Subject::create(['code' => 'LEN', 'name' => 'Lenguaje'])->id,
            'group_id' => $this->group->id,
            'teacher_id' => $this->teacher->id,
            'period' => 1,
        ]);

        ventana(['cycle_id' => $this->cycle->id, 'course_id' => $otra->id]);

        ($this->putScore)()->assertForbidden();
    });

    it('se vence sola al pasar la hora, con la pantalla abierta', function () {
        $window = ventana([
            'cycle_id' => $this->cycle->id,
            'course_id' => $this->course->id,
            'closes_at' => now()->addHour(),
        ]);

        $planilla = Livewire::actingAs($this->user)
            ->test(Gradebook::class, ['course' => $this->course])
            ->set("scores.{$this->student->id}", '4.5');

        $this->travelTo($window->closes_at->addMinute());

        $planilla->set("scores.{$this->student->id}", '2.0')->assertForbidden();

        expect((float) Grade::where('student_id', $this->student->id)->value('score'))->toBe(4.5);
    });

    it('el panel la abre con plazo y la cierra antes de tiempo', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $panel = Livewire::actingAs($admin)->test(TermClosure::class)
            ->call('openWindowForm', $this->cycle->id)
            ->set('courseId', (string) $this->course->id)
            ->set('closesAt', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->set('note', 'Corrección de la nota de Ana')
            ->call('openWindow');

        $window = EditWindow::sole();

        expect($window->course_id)->toBe($this->course->id)
            ->and($window->opened_by)->toBe($admin->id)
            ->and($window->note)->toBe('Corrección de la nota de Ana');

        ($this->putScore)();
        expect(Grade::count())->toBe(1);

        $panel->call('closeWindow', $window->id);

        expect($window->refresh()->isActive())->toBeFalse();

        ($this->putScore)()->assertForbidden();
    });

    it('no acepta una fecha que ya pasó', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(TermClosure::class)
            ->call('openWindowForm', $this->cycle->id)
            ->set('closesAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('openWindow')
            ->assertHasErrors('closesAt');

        expect(EditWindow::count())->toBe(0);
    });

    it('no acepta una materia de otro semestre', function () {
        $otroCiclo = Cycle::create(['code' => 'C6-2026-1', 'level' => '6', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 6']);

        $ajena = Course::create([
            'code' => 'C6MATM1S12026',
            'cycle_id' => $otroCiclo->id,
            'subject_id' => Subject::create(['code' => 'SOC', 'name' => 'Sociales'])->id,
            'period' => 1,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(TermClosure::class)
            ->call('openWindowForm', $this->cycle->id)
            ->set('courseId', (string) $ajena->id)
            ->call('openWindow')
            ->assertHasErrors('courseId');

        expect(EditWindow::count())->toBe(0);
    });
});

describe('datos personales del docente', function () {
    it('los ve pero no los edita ni puede borrar su cuenta', function () {
        $this->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($this->user->name)
            ->assertSee($this->user->email)
            ->assertSee('los actualiza la coordinación')
            ->assertDontSeeLivewire('settings.delete-user-form');

        Livewire::actingAs($this->user)->test(Profile::class)
            ->set('name', 'Otro Nombre')
            ->call('updateProfileInformation')
            ->assertForbidden();

        Livewire::actingAs($this->user)->test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertForbidden();

        expect($this->user->refresh()->name)->not->toBe('Otro Nombre')
            ->and(User::whereKey($this->user->id)->exists())->toBeTrue();
    });

    it('un administrador sí edita el suyo', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('name', 'Coordinación Académica')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        expect($admin->refresh()->name)->toBe('Coordinación Académica');
    });

    it('el docente que además es administrador conserva el control de su cuenta', function () {
        $this->user->assignRole('admin');

        Livewire::actingAs($this->user)->test(Profile::class)
            ->set('name', 'Juan Perez Coordinador')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        expect($this->user->refresh()->name)->toBe('Juan Perez Coordinador');
    });

    it('corregir la ficha del docente corrige con qué entra al sistema', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Teachers::class)
            ->call('openEdit', $this->teacher->id)
            ->set('first_name', 'Juana')
            ->set('email', 'juana.perez@iete.edu.co')
            ->call('save')
            ->assertHasNoErrors();

        expect($this->user->refresh()->email)->toBe('juana.perez@iete.edu.co')
            ->and($this->user->name)->toBe('Juana Perez');
    });

    it('un docente sin cuenta sí puede traer el correo de un usuario existente', function () {
        // Es como se vincula a alguien de administración que además dicta clase.
        $rectoria = User::factory()->create(['email' => 'rectoria@iete.edu.co']);

        $nuevo = Teacher::create(['username' => 'nsocorro', 'first_name' => 'Nelly', 'last_name' => 'Acevedo']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Teachers::class)
            ->call('openEdit', $nuevo->id)
            ->set('email', $rectoria->email)
            ->call('save')
            ->assertHasNoErrors();

        expect($nuevo->refresh()->email)->toBe($rectoria->email);
    });

    it('no le deja el correo de otra cuenta', function () {
        $otra = User::factory()->create(['email' => 'rectoria@iete.edu.co']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Teachers::class)
            ->call('openEdit', $this->teacher->id)
            ->set('email', $otra->email)
            ->call('save')
            ->assertHasErrors('email');

        expect($this->user->refresh()->email)->not->toBe($otra->email);
    });
});
