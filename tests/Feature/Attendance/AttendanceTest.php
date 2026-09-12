<?php

use App\Enums\AttendanceStatus;
use App\Livewire\Admin\Calendar;
use App\Livewire\Teacher\Attendance as TeacherAttendance;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BoletinService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('docente');
    Role::findOrCreate('admin');

    $this->cycle = Cycle::create([
        'code' => 'C4B-2026-2',
        'level' => '4B',
        'semester' => 2,
        'year' => 2026,
        'name' => 'Ciclo 4B',
    ]);

    $this->group = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciclo 4B1', 'code' => 'G1']);

    $this->user = User::factory()->create();
    $this->user->assignRole('docente');

    $this->teacher = Teacher::create([
        'username' => 'jperez',
        'first_name' => 'Juan',
        'last_name' => 'Perez',
        'email' => 'jperez@iete.edu.co',
    ]);
    $this->teacher->user()->associate($this->user)->save();

    $this->subject = Subject::create(['code' => 'MAT', 'name' => 'Matematicas']);

    $this->course = Course::create([
        'code' => 'C4B1MATM1S22026',
        'cycle_id' => $this->cycle->id,
        'subject_id' => $this->subject->id,
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
});

describe('calendario', function () {
    it('genera un día de clase por semana entre las dos fechas', function () {
        // Del sábado 15 de agosto al sábado 5 de septiembre son cuatro sábados.
        $this->cycle->update([
            'starts_on' => '2026-08-15',
            'ends_on' => '2026-09-05',
            'class_weekday' => 6,
        ]);

        $created = app(AttendanceService::class)->generateSessions($this->cycle);

        expect($created)->toBe(4)
            ->and($this->cycle->sessions()->pluck('date')->map->toDateString()->all())
            ->toBe(['2026-08-15', '2026-08-22', '2026-08-29', '2026-09-05']);
    });

    it('no duplica los días que ya existen', function () {
        $this->cycle->update(['starts_on' => '2026-08-15', 'ends_on' => '2026-08-29', 'class_weekday' => 6]);

        $service = app(AttendanceService::class);
        $service->generateSessions($this->cycle);

        expect($service->generateSessions($this->cycle))->toBe(0)
            ->and($this->cycle->sessions()->count())->toBe(3);
    });

    it('vuelve a poner un día borrado si se genera de nuevo', function () {
        // Es la contrapartida de quitar un festivo a mano: queda fuera hasta que
        // alguien vuelva a generar el calendario. Vale la pena tenerlo escrito.
        $this->cycle->update(['starts_on' => '2026-08-15', 'ends_on' => '2026-08-29', 'class_weekday' => 6]);

        $service = app(AttendanceService::class);
        $service->generateSessions($this->cycle);

        ClassSession::whereDate('date', '2026-08-22')->delete();

        expect($this->cycle->sessions()->count())->toBe(2)
            ->and($service->generateSessions($this->cycle))->toBe(1);
    });

    it('no hace nada si el ciclo no tiene calendario configurado', function () {
        expect(app(AttendanceService::class)->generateSessions($this->cycle))->toBe(0);
    });

    it('quitar un festivo pasa por una confirmación que dice qué se pierde', function () {
        $this->cycle->update(['starts_on' => '2026-08-15', 'ends_on' => '2026-08-22', 'class_weekday' => 6]);
        app(AttendanceService::class)->generateSessions($this->cycle);

        $session = ClassSession::whereDate('date', '2026-08-22')->sole();

        Attendance::create([
            'class_session_id' => $session->id,
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'status' => AttendanceStatus::Absent,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $panel = Livewire::actingAs($admin)->test(Calendar::class)
            ->set('cycleId', (string) $this->cycle->id)
            ->call('openDelete', $session->id)
            ->assertSet('showDeleteModal', true)
            ->assertSee('1 registro de asistencia');

        expect($this->cycle->sessions()->count())->toBe(2);

        $panel->call('removeSession');

        expect($this->cycle->sessions()->count())->toBe(1)
            ->and(Attendance::count())->toBe(0);
    });

    it('el panel genera el calendario desde el formulario', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Calendar::class)
            ->set('cycleId', (string) $this->cycle->id)
            ->set('class_weekday', '6')
            ->set('starts_on', '2026-08-15')
            ->set('ends_on', '2026-08-29')
            ->call('generate');

        expect($this->cycle->sessions()->count())->toBe(3);
    });
});

describe('planilla de asistencia del docente', function () {
    beforeEach(function () {
        $this->cycle->update(['starts_on' => '2026-08-15', 'ends_on' => '2026-08-29', 'class_weekday' => 6]);
        app(AttendanceService::class)->generateSessions($this->cycle);

        $this->session = ClassSession::orderBy('date')->first();
    });

    test('el docente no abre la asistencia de otro docente', function () {
        $other = Teacher::create(['username' => 'mlopez', 'first_name' => 'Maria', 'last_name' => 'Lopez']);

        $course = Course::create([
            'code' => 'C4B1LENM1S22026',
            'cycle_id' => $this->cycle->id,
            'subject_id' => Subject::create(['code' => 'LEN', 'name' => 'Lenguaje'])->id,
            'teacher_id' => $other->id,
            'period' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('teacher.attendance', $course))
            ->assertForbidden();
    });

    test('marcar a alguien guarda la planilla entera, para que la clase quede registrada', function () {
        $otro = Student::create(['document' => '222', 'first_name' => 'Beto', 'last_name' => 'Ruiz', 'email' => 'beto@example.com']);
        Enrollment::create(['student_id' => $otro->id, 'cycle_id' => $this->cycle->id, 'group_id' => $this->group->id]);

        Livewire::actingAs($this->user)->test(TeacherAttendance::class, ['course' => $this->course])
            ->set('sessionId', (string) $this->session->id)
            ->set("status.{$this->student->id}", AttendanceStatus::Absent->value);

        expect(Attendance::count())->toBe(2)
            ->and(Attendance::where('student_id', $this->student->id)->value('status'))->toBe(AttendanceStatus::Absent)
            ->and(Attendance::where('student_id', $otro->id)->value('status'))->toBe(AttendanceStatus::Present);
    });

    test('no acepta marcar a alguien que no está matriculado', function () {
        $ajeno = Student::create(['document' => '999', 'first_name' => 'Otro', 'last_name' => 'Ciclo', 'email' => 'otro@example.com']);

        Livewire::actingAs($this->user)->test(TeacherAttendance::class, ['course' => $this->course])
            ->set('sessionId', (string) $this->session->id)
            ->set("status.{$ajeno->id}", AttendanceStatus::Absent->value)
            ->assertForbidden();
    });

    test('abre en la clase más reciente que ya pasó', function () {
        $this->travelTo('2026-08-23');

        Livewire::actingAs($this->user)->test(TeacherAttendance::class, ['course' => $this->course])
            ->assertSet('sessionId', (string) ClassSession::whereDate('date', '2026-08-22')->value('id'));
    });
});

describe('conteo de fallas', function () {
    beforeEach(function () {
        $this->cycle->update(['starts_on' => '2026-08-15', 'ends_on' => '2026-10-17', 'class_weekday' => 6]);
        app(AttendanceService::class)->generateSessions($this->cycle);
        $this->sessions = ClassSession::orderBy('date')->get();
    });

    /**
     * @param  list<AttendanceStatus>  $statuses
     */
    function registrar(int $courseId, int $studentId, $sessions, array $statuses): void
    {
        foreach ($statuses as $i => $status) {
            Attendance::create([
                'class_session_id' => $sessions[$i]->id,
                'course_id' => $courseId,
                'student_id' => $studentId,
                'status' => $status,
            ]);
        }
    }

    it('no reprocha nada mientras nadie haya pasado asistencia', function () {
        $line = app(AttendanceService::class)->byCourse($this->student->id, collect([$this->course]))[$this->course->id];

        expect($line['held'])->toBe(0)
            ->and($line['rate'])->toBeNull()
            ->and($line['lost'])->toBeFalse();
    });

    it('marca la pérdida al pasar del tope y no antes', function () {
        // Diez clases dictadas: con dos fallas va justo en el tope del 20%.
        $statuses = array_fill(0, 10, AttendanceStatus::Present);
        $statuses[0] = AttendanceStatus::Absent;
        $statuses[1] = AttendanceStatus::Absent;

        registrar($this->course->id, $this->student->id, $this->sessions, $statuses);

        $service = app(AttendanceService::class);
        $line = $service->byCourse($this->student->id, collect([$this->course]))[$this->course->id];

        expect($line['absences'])->toBe(2)
            ->and($line['rate'])->toBe(0.2)
            ->and($line['lost'])->toBeFalse();

        Attendance::where('class_session_id', $this->sessions[2]->id)->update(['status' => AttendanceStatus::Absent]);

        expect($service->byCourse($this->student->id, collect([$this->course]))[$this->course->id]['lost'])->toBeTrue();
    });

    it('no cuenta las justificadas contra la materia', function () {
        $statuses = array_fill(0, 5, AttendanceStatus::Excused);

        registrar($this->course->id, $this->student->id, $this->sessions, $statuses);

        $line = app(AttendanceService::class)->byCourse($this->student->id, collect([$this->course]))[$this->course->id];

        expect($line['excused'])->toBe(5)
            ->and($line['absences'])->toBe(0)
            ->and($line['lost'])->toBeFalse();
    });

    it('suma los dos periodos de una materia en una sola línea', function () {
        $segundo = Course::create([
            'code' => 'C4B1MATM2S22026',
            'cycle_id' => $this->cycle->id,
            'subject_id' => $this->subject->id,
            'group_id' => $this->group->id,
            'teacher_id' => $this->teacher->id,
            'period' => 2,
        ]);

        registrar($this->course->id, $this->student->id, $this->sessions, [AttendanceStatus::Absent, AttendanceStatus::Present]);
        registrar($segundo->id, $this->student->id, $this->sessions, [AttendanceStatus::Absent, AttendanceStatus::Present]);

        $line = app(AttendanceService::class)
            ->bySubject($this->student->id, collect([$this->course, $segundo]))[$this->subject->id];

        expect($line['held'])->toBe(4)
            ->and($line['absences'])->toBe(2);
    });

    it('la matriz del panel da lo mismo que materia por materia', function () {
        registrar($this->course->id, $this->student->id, $this->sessions, [AttendanceStatus::Absent, AttendanceStatus::Present]);

        $service = app(AttendanceService::class);
        $courses = collect([$this->course]);

        expect($service->matrix([$this->student->id], $courses)[$this->student->id])
            ->toBe($service->bySubject($this->student->id, $courses));
    });
});

describe('boletín', function () {
    it('lleva las fallas de cada materia y marca la pérdida por inasistencia', function () {
        $this->cycle->update(['starts_on' => '2026-08-15', 'ends_on' => '2026-09-05', 'class_weekday' => 6]);
        app(AttendanceService::class)->generateSessions($this->cycle);

        Grade::create(['course_id' => $this->course->id, 'student_id' => $this->student->id, 'score' => 4.0]);

        // Tres de cuatro clases perdidas: muy por encima del tope.
        foreach (ClassSession::orderBy('date')->get() as $i => $session) {
            Attendance::create([
                'class_session_id' => $session->id,
                'course_id' => $this->course->id,
                'student_id' => $this->student->id,
                'status' => $i === 0 ? AttendanceStatus::Present : AttendanceStatus::Absent,
            ]);
        }

        $boletin = app(BoletinService::class)->generate($this->student, $this->cycle);
        $line = collect($boletin['lines'])->firstWhere('name', 'Matematicas');

        expect($line['absences'])->toBe(3)
            ->and($line['held'])->toBe(4)
            ->and($line['lost_by_absence'])->toBeTrue()
            // La nota sigue siendo la que es: la pérdida por inasistencia es aparte.
            ->and($line['final'])->toBe(4.0);
    });
});
