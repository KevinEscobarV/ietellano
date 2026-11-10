<?php

use App\Enums\AttendanceStatus;
use App\Livewire\Teacher\MyCourses;
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
use App\Services\ConsolidadoExporter;
use App\Services\ConsolidadoService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * @return array<string, string> nombre de la hoja => XML
 */
function teacherSheets(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);

    $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $sheets = [];
    $index = 1;

    foreach ($workbook->sheets->sheet as $sheet) {
        $sheets[(string) $sheet['name']] = $zip->getFromName("xl/worksheets/sheet{$index}.xml");
        $index++;
    }

    $zip->close();

    return $sheets;
}

beforeEach(function () {
    Role::findOrCreate('docente');

    $this->cycle = Cycle::create(['code' => 'C1-2026-2', 'level' => '1', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 1']);
    $this->groupA = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Grupo A', 'code' => 'GA']);
    $this->groupB = Group::create(['cycle_id' => $this->cycle->id, 'name' => 'Grupo B', 'code' => 'GB']);

    $this->user = User::factory()->create();
    $this->user->assignRole('docente');

    $this->teacher = Teacher::create(['username' => 'jperez', 'first_name' => 'Juan', 'last_name' => 'Perez', 'email' => 'jperez@iete.edu.co']);
    $this->teacher->user()->associate($this->user)->save();

    $other = Teacher::create(['username' => 'mlopez', 'first_name' => 'Maria', 'last_name' => 'Lopez']);

    $math = Subject::create(['code' => 'MAT', 'name' => 'Matematicas']);
    $language = Subject::create(['code' => 'LEN', 'name' => 'Lenguaje']);

    // Juan dicta Matemáticas en el grupo A; Lenguaje y el grupo B son de otra.
    $course = fn (Subject $subject, Group $group, int $period, Teacher $teacher) => Course::create([
        'code' => "{$subject->code}-{$group->code}-{$period}",
        'cycle_id' => $this->cycle->id,
        'subject_id' => $subject->id,
        'group_id' => $group->id,
        'teacher_id' => $teacher->id,
        'period' => $period,
    ]);

    $this->mathA1 = $course($math, $this->groupA, 1, $this->teacher);
    $this->mathA2 = $course($math, $this->groupA, 2, $this->teacher);
    $this->mathB1 = $course($math, $this->groupB, 1, $other);
    $this->languageA1 = $course($language, $this->groupA, 1, $other);

    $student = function (string $last, string $first, Group $group): Student {
        $student = Student::create(['document' => (string) fake()->unique()->numberBetween(1000, 9999), 'first_name' => $first, 'last_name' => $last, 'email' => strtolower($first).'@example.com']);
        Enrollment::create(['student_id' => $student->id, 'cycle_id' => $this->cycle->id, 'group_id' => $group->id]);

        return $student;
    };

    $this->ana = $student('GOMEZ', 'ANA', $this->groupA);
    $this->luis = $student('RUIZ', 'LUIS', $this->groupA);
    $this->outsider = $student('ZAPATA', 'ZOE', $this->groupB);

    Grade::create(['course_id' => $this->mathA1->id, 'student_id' => $this->ana->id, 'score' => 4.0]);
    Grade::create(['course_id' => $this->mathA2->id, 'student_id' => $this->ana->id, 'score' => 3.0]);
    Grade::create(['course_id' => $this->mathA1->id, 'student_id' => $this->luis->id, 'score' => 2.0]);
    Grade::create(['course_id' => $this->languageA1->id, 'student_id' => $this->ana->id, 'score' => 1.0]);
    Grade::create(['course_id' => $this->mathB1->id, 'student_id' => $this->outsider->id, 'score' => 5.0]);

    // Ana faltó a una de las dos clases de Matemáticas.
    foreach (['2026-08-01' => AttendanceStatus::Absent, '2026-08-08' => AttendanceStatus::Present] as $date => $status) {
        $session = ClassSession::create(['cycle_id' => $this->cycle->id, 'date' => $date]);
        Attendance::create(['class_session_id' => $session->id, 'course_id' => $this->mathA1->id, 'student_id' => $this->ana->id, 'status' => $status]);
    }
});

it('trae solo las materias y los grupos del docente', function () {
    $data = app(ConsolidadoService::class)->forTeacher($this->teacher, $this->cycle);

    expect($data['sections'])->toHaveCount(1)
        ->and($data['sections'][0])->toMatchArray(['subject' => 'Matematicas', 'group' => 'Grupo A', 'periods' => [1, 2]])
        ->and(array_column($data['sections'][0]['rows'], 'name'))->toBe(['GOMEZ ANA', 'RUIZ LUIS']);
});

it('da por estudiante las dos notas, la definitiva y las fallas', function () {
    $section = app(ConsolidadoService::class)->forTeacher($this->teacher, $this->cycle)['sections'][0];
    [$ana, $luis] = $section['rows'];

    expect($ana)->toMatchArray(['m1' => 4.0, 'm2' => 3.0, 'final' => 3.5, 'complete' => true, 'failed' => false, 'performance' => 'Básico', 'absences' => 1])
        ->and($luis)->toMatchArray(['m1' => 2.0, 'm2' => null, 'final' => 2.0, 'complete' => false, 'failed' => true, 'absences' => 0])
        ->and($section['stats'])->toMatchArray(['students' => 2, 'final' => 2.75, 'passed' => 1, 'failed' => 1, 'pending' => 1]);
});

it('el docente descarga su consolidado en Excel', function () {
    $response = $this->actingAs($this->user)
        ->get(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-type', ConsolidadoExporter::XLSX_MIME)
        ->assertDownload('consolidado-juan-perez-ciclo-1-2026-2.xlsx');

    $path = $response->baseResponse->getFile()->getPathname();
    $sheets = teacherSheets($path);
    unlink($path);

    expect(array_keys($sheets))->toBe(['Matematicas Grupo A'])
        ->and($sheets['Matematicas Grupo A'])->toContain('GOMEZ ANA', 'Docente: Juan Perez', '<v>3.5</v>', 'PROVISIONAL')
        ->not->toContain('ZAPATA ZOE')
        ->not->toContain('Lenguaje')
        // Al imprimir cabe en el ancho de la hoja y los renglones de texto no
        // se salen de la tabla.
        ->and($sheets['Matematicas Grupo A'])->toContain('<pageSetUpPr fitToPage="1"/>', 'orientation="portrait"', '<mergeCell ref="A1:H1"/>', '<mergeCell ref="A3:H3"/>');
});

it('y en PDF', function () {
    $this->actingAs($this->user)
        ->get(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'pdf']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('un ciclo donde no dicta nada no existe para él', function () {
    $other = Cycle::create(['code' => 'C2-2026-2', 'level' => '2', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 2']);

    $this->actingAs($this->user)
        ->get(route('teacher.consolidado', ['cycle' => $other->id]))
        ->assertNotFound();
});

it('solo hay Excel y PDF', function () {
    $this->actingAs($this->user)
        ->get(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'csv']))
        ->assertNotFound();
});

it('una cuenta que no es de docente no entra', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('teacher.consolidado', ['cycle' => $this->cycle->id]))
        ->assertForbidden();
});

it('en el segundo semestre de un ciclo de un año suma la definitiva del primero y la del año', function () {
    $this->cycle->update(['level' => '1B', 'name' => 'Ciclo 1B']);
    $previous = Cycle::create(['code' => 'C1A-2026-1', 'level' => '1A', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 1A']);
    $this->cycle->update(['previous_cycle_id' => $previous->id]);

    $math = Subject::where('code', 'MAT')->first();
    $earlier = collect([1, 2])->map(fn (int $period) => Course::create([
        'code' => "MAT-1A-{$period}",
        'cycle_id' => $previous->id,
        'subject_id' => $math->id,
        'period' => $period,
    ]));

    // Ana cursó 1A (2.0 y 2.0); Luis entró en 1B.
    Enrollment::create(['student_id' => $this->ana->id, 'cycle_id' => $previous->id]);
    $earlier->each(fn (Course $course) => Grade::create(['course_id' => $course->id, 'student_id' => $this->ana->id, 'score' => 2.0]));

    $data = app(ConsolidadoService::class)->forTeacher($this->teacher, $this->cycle->fresh());
    $section = $data['sections'][0];
    [$ana, $luis] = $section['rows'];

    expect($data['yearParts'])->toBe(['previous' => '1A', 'current' => '1B'])
        ->and($section['year'])->toBeTrue()
        // 1B: 3.5; 1A: 2.0; año: 2.75, que pierde.
        ->and($ana)->toMatchArray(['final' => 3.5, 'took_previous' => true, 'previous' => 2.0, 'year' => 2.75, 'year_failed' => true, 'performance' => 'Bajo'])
        ->and($luis)->toMatchArray(['took_previous' => false, 'previous' => null, 'year' => null])
        ->and($section['stats'])->toMatchArray(['year' => 2.75, 'year_failed' => 1, 'without_previous' => 1]);

    $response = $this->actingAs($this->user)
        ->get(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'xlsx']))
        ->assertOk();

    $path = $response->baseResponse->getFile()->getPathname();
    $sheet = teacherSheets($path)['Matematicas Grupo A'];
    unlink($path);

    expect($sheet)->toContain('Def. 1B', 'Def. 1A', 'Año', 'No cursó', '<v>2.75</v>');

    $this->actingAs($this->user)
        ->get(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'pdf']))
        ->assertOk();
});

it('Mis materias ofrece el consolidado de cada ciclo', function () {
    Livewire::actingAs($this->user)
        ->test(MyCourses::class)
        ->assertSee('Consolidado')
        ->assertSee(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'xlsx']), false)
        ->assertSee(route('teacher.consolidado', ['cycle' => $this->cycle->id, 'formato' => 'pdf']), false);
});
