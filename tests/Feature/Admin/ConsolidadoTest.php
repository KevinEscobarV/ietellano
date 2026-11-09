<?php

use App\Livewire\Admin\Consolidados;
use App\Models\Area;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\BoletinService;
use App\Services\ConsolidadoExporter;
use App\Services\ConsolidadoService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Las hojas de un .xlsx descargado: nombre => XML de la hoja.
 *
 * @return array<string, string>
 */
function consolidadoSheets(string $path): array
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
    Role::findOrCreate('admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->cycle = Cycle::create(['code' => 'C5-2026-2', 'level' => '5', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 5']);

    $this->g1 = Group::create(['cycle_id' => $this->cycle->id, 'name' => '5-1', 'code' => 'G51']);
    $this->g2 = Group::create(['cycle_id' => $this->cycle->id, 'name' => '5-2', 'code' => 'G52']);

    $subjects = collect([
        'MAT' => 'Matemáticas',
        'LEN' => 'Lengua Castellana',
        'FIS' => 'Física',
        'QUI' => 'Química',
        'PV' => 'Proyecto de Vida',
    ])->map(fn (string $name, string $code) => Subject::create(['code' => $code, 'name' => $name]));

    // Física y Química se leen juntas, como en el boletín de los ciclos 5 y 6.
    Area::create(['cycle_id' => $this->cycle->id, 'name' => 'Ciencias Naturales', 'code' => 'CN', 'display_order' => 1])
        ->subjects()->attach([$subjects['FIS']->id, $subjects['QUI']->id]);

    // Proyecto de Vida es solo del grupo 5-1.
    $this->courses = [];

    foreach ($subjects as $code => $subject) {
        foreach ([1, 2] as $period) {
            $this->courses["{$code}{$period}"] = Course::create([
                'code' => "C5{$code}M{$period}",
                'cycle_id' => $this->cycle->id,
                'subject_id' => $subject->id,
                'group_id' => $code === 'PV' ? $this->g1->id : null,
                'period' => $period,
            ]);
        }
    }

    $this->enroll = function (string $last, string $first, Group $group, array $grades, ?Cycle $cycle = null): Student {
        $student = Student::create([
            'document' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first).'@example.com',
        ]);

        Enrollment::create(['student_id' => $student->id, 'cycle_id' => ($cycle ?? $this->cycle)->id, 'group_id' => $group->id]);

        foreach ($grades as $course => $score) {
            Grade::create(['course_id' => $this->courses[$course]->id, 'student_id' => $student->id, 'score' => $score]);
        }

        return $student;
    };

    // Ana pierde Lengua (2.2); Beto pierde Ciencias Naturales (2.25); a Carla
    // le falta la nota del M2 de Matemáticas.
    $this->ana = ($this->enroll)('AREVALO', 'ANA', $this->g1, [
        'MAT1' => 4.0, 'MAT2' => 5.0, 'LEN1' => 2.0, 'LEN2' => 2.4,
        'FIS1' => 3.0, 'FIS2' => 3.0, 'QUI1' => 4.0, 'QUI2' => 4.0, 'PV1' => 3.0, 'PV2' => 3.0,
    ]);
    $this->beto = ($this->enroll)('BELTRAN', 'BETO', $this->g2, [
        'MAT1' => 3.0, 'MAT2' => 3.0, 'LEN1' => 3.0, 'LEN2' => 4.0,
        'FIS1' => 2.0, 'FIS2' => 2.0, 'QUI1' => 2.0, 'QUI2' => 3.0,
    ]);
    $this->carla = ($this->enroll)('CASTRO', 'CARLA', $this->g2, [
        'MAT1' => 4.0, 'LEN1' => 4.0, 'LEN2' => 4.0,
        'FIS1' => 4.0, 'FIS2' => 4.0, 'QUI1' => 4.0, 'QUI2' => 4.0,
    ]);

    $this->consolidado = fn (...$args) => app(ConsolidadoService::class)->forCycle($this->cycle->fresh(), ...$args);
    $this->row = fn (array $consolidado, Student $student) => collect($consolidado['rows'])->firstWhere('id', $student->id);
});

describe('el consolidado del ciclo', function () {
    it('trae una fila por estudiante con la definitiva de cada línea del boletín', function () {
        $consolidado = ($this->consolidado)();
        $ana = ($this->row)($consolidado, $this->ana);

        expect(array_column($consolidado['columns'], 'name'))->toBe(['Ciencias Naturales', 'Lengua Castellana', 'Matemáticas', 'Proyecto de Vida'])
            ->and($consolidado['columns'][0]['components'])->toEqualCanonicalizing(['Física', 'Química'])
            ->and($ana['cells']['Matemáticas'])->toMatchArray(['score' => 4.5, 'first' => 4.0, 'second' => 5.0, 'complete' => true, 'failed' => false])
            ->and($ana['cells']['Lengua Castellana'])->toMatchArray(['score' => 2.2, 'failed' => true])
            ->and($ana['cells']['Ciencias Naturales']['score'])->toBe(3.5)
            ->and($ana)->toMatchArray(['average' => 3.3, 'failed' => 1, 'pending' => 0, 'group' => '5-1'])
            // El promedio es el mismo del boletín.
            ->and($ana['average'])->toBe(app(BoletinService::class)->generate($this->ana, $this->cycle)['overall']);
    });

    it('deja en blanco la materia que no le toca al estudiante', function () {
        $beto = ($this->row)(($this->consolidado)(), $this->beto);

        expect($beto['cells'])->not->toHaveKey('Proyecto de Vida')
            ->and($beto['cells']['Ciencias Naturales'])->toMatchArray(['score' => 2.25, 'failed' => true])
            ->and($beto['average'])->toBe(2.92);
    });

    it('marca incompleta la definitiva a la que le falta un módulo', function () {
        $carla = ($this->row)(($this->consolidado)(), $this->carla);

        expect($carla['cells']['Matemáticas'])->toMatchArray(['score' => 4.0, 'second' => null, 'complete' => false])
            ->and($carla['pending'])->toBe(1);
    });

    it('resume el ciclo para leerlo de un vistazo', function () {
        $consolidado = ($this->consolidado)();
        $summary = $consolidado['summary'];

        expect($summary)->toMatchArray([
            'students' => 3,
            'graded' => 3,
            'average' => 3.41,
            'performance' => 'Básico',
            'all_passed' => 1,
            'failing' => [1 => 2, 2 => 0, 3 => 0],
            'failing_total' => 2,
            'levels' => ['Superior' => 0, 'Alto' => 1, 'Básico' => 1, 'Bajo' => 1],
            'pending_students' => 1,
            'pending_cells' => 1,
        ])
            ->and(array_column($summary['hardest'], 'name'))->toEqualCanonicalizing(['Ciencias Naturales', 'Lengua Castellana'])
            ->and($consolidado['stats']['Matemáticas'])->toMatchArray(['students' => 3, 'average' => 3.83, 'passed' => 3, 'failed' => 0, 'pending' => 1])
            ->and($consolidado['stats']['Proyecto de Vida']['students'])->toBe(1);
    });

    it('da el puesto por promedio y los empatados lo comparten', function () {
        $dora = ($this->enroll)('DIAZ', 'DORA', $this->g1, [
            'MAT1' => 4.0, 'MAT2' => 5.0, 'LEN1' => 2.0, 'LEN2' => 2.4,
            'FIS1' => 3.0, 'FIS2' => 3.0, 'QUI1' => 4.0, 'QUI2' => 4.0, 'PV1' => 3.0, 'PV2' => 3.0,
        ]);

        $consolidado = ($this->consolidado)('definitiva', false, null, 'puesto');
        $ranks = collect($consolidado['rows'])->mapWithKeys(fn (array $row) => [$row['name'] => $row['rank']])->all();

        expect($ranks)->toBe([
            'CASTRO CARLA' => 1,
            'AREVALO ANA' => 2,
            'DIAZ DORA' => 2,
            'BELTRAN BETO' => 4,
        ]);
    });

    it('lee un solo módulo', function () {
        $m1 = ($this->consolidado)('m1');
        $m2 = ($this->consolidado)('m2');

        expect(($this->row)($m1, $this->ana)['cells']['Lengua Castellana'])->toMatchArray(['score' => 2.0, 'failed' => true, 'complete' => true])
            ->and(($this->row)($m1, $this->carla)['cells']['Matemáticas'])->toMatchArray(['score' => 4.0, 'complete' => true])
            ->and(($this->row)($m2, $this->carla)['cells']['Matemáticas'])->toMatchArray(['score' => null, 'complete' => false])
            ->and($m1['title'])->toBe('Notas del Módulo 1');
    });

    it('filtra por grupo y saca el puesto dentro del grupo', function () {
        $consolidado = ($this->consolidado)('definitiva', false, $this->g2->id);

        expect(array_column($consolidado['rows'], 'name'))->toBe(['BELTRAN BETO', 'CASTRO CARLA'])
            ->and(($this->row)($consolidado, $this->carla)['rank'])->toBe(1)
            ->and(($this->row)($consolidado, $this->beto)['rank'])->toBe(2)
            ->and($consolidado['showGroup'])->toBeFalse()
            ->and($consolidado['scope'])->toBe('Ciclo 5 · 2026-2 · 5-2');
    });

    it('un grupo de otro ciclo no filtra nada', function () {
        $other = Cycle::create(['code' => 'C6-2026-2', 'level' => '6', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 6']);
        $foreign = Group::create(['cycle_id' => $other->id, 'name' => '6', 'code' => 'G6']);

        $consolidado = ($this->consolidado)('definitiva', false, $foreign->id);

        expect($consolidado['group'])->toBeNull()
            ->and($consolidado['rows'])->toHaveCount(3)
            ->and($consolidado['showGroup'])->toBeTrue();
    });

    it('con los dos semestres promedia la definitiva de cada uno', function () {
        $previous = Cycle::create(['code' => 'C5-2026-1', 'level' => '5', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 5']);
        $previousGroup = Group::create(['cycle_id' => $previous->id, 'name' => '5-1', 'code' => 'P51']);
        $this->cycle->update(['previous_cycle_id' => $previous->id]);

        foreach ([1, 2] as $period) {
            $this->courses["PMAT{$period}"] = Course::create([
                'code' => "P5MATM{$period}",
                'cycle_id' => $previous->id,
                'subject_id' => Subject::where('code', 'MAT')->value('id'),
                'period' => $period,
            ]);
        }

        Enrollment::create(['student_id' => $this->ana->id, 'cycle_id' => $previous->id, 'group_id' => $previousGroup->id]);
        Grade::create(['course_id' => $this->courses['PMAT1']->id, 'student_id' => $this->ana->id, 'score' => 3.0]);
        Grade::create(['course_id' => $this->courses['PMAT2']->id, 'student_id' => $this->ana->id, 'score' => 3.0]);

        $consolidado = ($this->consolidado)('m1', true);

        // Solo entra quien cursó los dos semestres, y no hay vista por módulo.
        expect(array_column($consolidado['rows'], 'name'))->toBe(['AREVALO ANA'])
            ->and($consolidado)->toMatchArray(['both' => true, 'view' => 'definitiva', 'title' => 'Definitivas de los dos semestres'])
            ->and($consolidado['rows'][0]['cells']['Matemáticas'])->toMatchArray(['first' => 3.0, 'second' => 4.5, 'score' => 3.75, 'complete' => true])
            ->and($consolidado['detailLabels'])->toBe(['Sem. 1', 'Sem. 2', 'Final']);
    });
});

describe('provisional o definitivo', function () {
    it('es provisional mientras haya módulos abiertos', function () {
        expect(($this->consolidado)()['status'])->toMatchArray([
            'definitive' => false,
            'label' => 'Provisional',
            'detail' => 'Los módulos 1 y 2 siguen abiertos',
        ]);
    });

    it('con el M1 cerrado, el M1 ya es definitivo y la definitiva todavía no', function () {
        $this->cycle->closeModule(1);

        expect(($this->consolidado)('m1')['status']['definitive'])->toBeTrue()
            ->and(($this->consolidado)()['status'])->toMatchArray(['definitive' => false, 'detail' => 'El Módulo 2 sigue abierto']);
    });

    it('dice hasta cuándo sigue abierto si el cierre está programado', function () {
        $this->cycle->closeModule(1);
        $this->cycle->scheduleModuleClosure(2, now()->addWeek()->setTime(18, 0));

        expect(($this->consolidado)()['status']['detail'])->toBe('El Módulo 2 sigue abierto hasta el '.now()->addWeek()->translatedFormat('j \d\e F \d\e Y'));
    });

    it('es definitivo con el semestre cerrado', function () {
        $this->cycle->close();

        expect(($this->consolidado)()['status'])->toMatchArray(['definitive' => true, 'label' => 'Definitivo'])
            ->and(($this->consolidado)()['status']['detail'])->toStartWith('Cerrado el ');
    });
});

describe('la pantalla', function () {
    it('abre en el ciclo más reciente con el resumen y la tabla', function () {
        Livewire::actingAs($this->admin)
            ->test(Consolidados::class)
            ->assertSet('cycleId', (string) $this->cycle->id)
            ->assertSee('AREVALO ANA')
            ->assertSee('Provisional')
            ->assertSee('Aprueban todo')
            ->assertSee('Ciencias Naturales');
    });

    it('cambia de vista, de grupo y de orden, y descarga lo mismo que muestra', function () {
        Livewire::actingAs($this->admin)
            ->test(Consolidados::class)
            ->set('view', 'm2')
            ->set('groupId', (string) $this->g2->id)
            ->set('sort', 'puesto')
            ->assertViewHas('consolidado', fn (array $c) => $c['view'] === 'm2' && count($c['rows']) === 2)
            ->assertViewHas('downloadParams', ['vista' => 'm2', 'grupo' => $this->g2->id, 'orden' => 'puesto']);
    });

    it('con los dos semestres vuelve a la definitiva', function () {
        $previous = Cycle::create(['code' => 'C5-2026-1', 'level' => '5', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 5']);
        $this->cycle->update(['previous_cycle_id' => $previous->id]);

        Livewire::actingAs($this->admin)
            ->test(Consolidados::class)
            ->set('view', 'm1')
            ->set('bothSemesters', true)
            ->assertSet('view', 'definitiva')
            ->assertViewHas('downloadParams', ['dos' => 1]);
    });

    it('cambiar de ciclo suelta el grupo', function () {
        $other = Cycle::create(['code' => 'C6-2026-2', 'level' => '6', 'semester' => 2, 'year' => 2026, 'name' => 'Ciclo 6']);

        Livewire::actingAs($this->admin)
            ->test(Consolidados::class)
            ->set('groupId', (string) $this->g1->id)
            ->set('cycleId', (string) $other->id)
            ->assertSet('groupId', '')
            ->assertSee('Este ciclo no tiene estudiantes matriculados.');
    });

    it('aparece en el menú de la coordinación', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.consolidados'))
            ->assertOk()
            ->assertSee(route('admin.consolidados'), false);
    });
});

describe('descargas', function () {
    it('el Excel trae la tabla y el detalle por módulo', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', ConsolidadoExporter::XLSX_MIME)
            ->assertDownload('consolidado-ciclo-5-2026-2.xlsx');

        $path = $response->baseResponse->getFile()->getPathname();
        $sheets = consolidadoSheets($path);
        unlink($path);

        expect(array_keys($sheets))->toBe(['Consolidado', 'Detalle por módulo'])
            ->and($sheets['Consolidado'])->toContain('AREVALO ANA', 'PROVISIONAL', 'Ciencias Naturales', '<v>4.5</v>')
            ->and($sheets['Detalle por módulo'])->toContain('<v>2.4</v>');
    });

    it('el archivo dice qué se descargó', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'xlsx', 'vista' => 'm1', 'grupo' => $this->g2->id]))
            ->assertOk()
            ->assertDownload('consolidado-ciclo-5-2026-2-5-2-modulo-1.xlsx');
    });

    it('el PDF sale para imprimir', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('consolidado-ciclo-5-2026-2.pdf');
    });

    it('todo el semestre: una hoja de resumen y una por ciclo', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download-term', ['year' => 2026, 'semester' => 2, 'formato' => 'xlsx']))
            ->assertOk()
            ->assertDownload('consolidado-2026-2.xlsx');

        $path = $response->baseResponse->getFile()->getPathname();
        $sheets = consolidadoSheets($path);
        unlink($path);

        expect(array_keys($sheets))->toBe(['Resumen', 'Ciclo 5'])
            ->and($sheets['Resumen'])->toContain('Ciclo 5', 'Provisional');

        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download-term', ['year' => 2026, 'semester' => 2, 'formato' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('un semestre sin estudiantes no tiene consolidado', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download-term', ['year' => 2020, 'semester' => 1]))
            ->assertNotFound();
    });

    it('solo hay Excel y PDF', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'csv']))
            ->assertNotFound();
    });

    it('es solo para la coordinación', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id]))
            ->assertForbidden();
    });
});
