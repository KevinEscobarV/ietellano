<?php

use App\Enums\AttendanceStatus;
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

    $this->close = fn () => $this->cycle->update(['closed_at' => now()]);

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
            ->assertSee('Este semestre está cerrado')
            ->assertDontSee('Guardar todo');
    });

    it('no se cierra de un clic: primero pregunta', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $panel = Livewire::actingAs($admin)->test(TermClosure::class)
            ->call('confirm', 'close', $this->cycle->id)
            ->assertSet('showConfirmModal', true)
            // El aviso dice sobre qué semestre es, que un confirm del navegador no podía.
            ->assertSee($this->cycle->label);

        expect($this->cycle->refresh()->isClosed())->toBeFalse();

        $panel->call('confirmed');

        expect($this->cycle->refresh()->isClosed())->toBeTrue();
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

        Livewire::actingAs($admin)->test(TermClosure::class)->call('reopenCycle', $this->cycle->id);

        expect($this->cycle->refresh()->isClosed())->toBeFalse();

        ($this->putScore)();

        expect(Grade::count())->toBe(1);
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
