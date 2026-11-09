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

});

/**
 * Convierte el ciclo de prueba en 3B, el segundo semestre de un ciclo de un
 * año, y le arma su 3A: Ana y Beto cursaron los dos; Carla entró en 3B y
 * Diana cursó 3A y no siguió.
 */
function makeYearLongCycle(object $test): Cycle
{
    $test->cycle->update(['level' => '3B', 'name' => 'Ciclo 3B']);

    $previous = Cycle::create(['code' => 'C3A-2026-1', 'level' => '3A', 'semester' => 1, 'year' => 2026, 'name' => 'Ciclo 3A']);
    $group = Group::create(['cycle_id' => $previous->id, 'name' => '3A-1', 'code' => 'G3A']);
    $test->cycle->update(['previous_cycle_id' => $previous->id]);

    foreach (['MAT', 'LEN'] as $code) {
        foreach ([1, 2] as $period) {
            $test->courses["P{$code}{$period}"] = Course::create([
                'code' => "C3A{$code}M{$period}",
                'cycle_id' => $previous->id,
                'subject_id' => Subject::where('code', $code)->value('id'),
                'period' => $period,
            ]);
        }
    }

    $test->diana = Student::create(['document' => '777', 'first_name' => 'DIANA', 'last_name' => 'DUARTE', 'email' => 'diana@example.com']);

    $grades = [
        [$test->ana, ['PMAT1' => 3.0, 'PMAT2' => 3.0, 'PLEN1' => 4.0, 'PLEN2' => 4.0]],
        [$test->beto, ['PMAT1' => 2.0, 'PMAT2' => 2.0, 'PLEN1' => 3.0, 'PLEN2' => 3.0]],
        [$test->diana, ['PMAT1' => 1.0, 'PMAT2' => 1.0, 'PLEN1' => 2.0, 'PLEN2' => 2.0]],
    ];

    foreach ($grades as [$student, $scores]) {
        Enrollment::create(['student_id' => $student->id, 'cycle_id' => $previous->id, 'group_id' => $group->id]);

        foreach ($scores as $course => $score) {
            Grade::create(['course_id' => $test->courses[$course]->id, 'student_id' => $student->id, 'score' => $score]);
        }
    }

    return $previous;
}

describe('ciclos de un año (3A y 3B)', function () {
    beforeEach(function () {
        $this->previous = makeYearLongCycle($this);
    });

    it('lee el año completo con todos los estudiantes del segundo semestre', function () {
        $consolidado = ($this->consolidado)('definitiva', true);

        expect(array_column($consolidado['rows'], 'name'))->toBe(['AREVALO ANA', 'BELTRAN BETO', 'CASTRO CARLA'])
            ->and($consolidado)->toMatchArray([
                'year' => true,
                'title' => 'Definitivas del año',
                'scope' => 'Ciclo 3 · 3A (2026-1) y 3B (2026-2)',
                'yearParts' => ['previous' => '3A', 'current' => '3B'],
                'detailLabels' => ['3A', '3B', 'Año'],
            ]);
    });

    it('la nota del año es el promedio de la definitiva de los dos semestres', function () {
        $ana = ($this->row)(($this->consolidado)('definitiva', true), $this->ana);

        // Perdió Lengua en 3B (2.2), pero con el 4.0 de 3A la aprueba en el año.
        expect($ana['cells']['Matemáticas'])->toMatchArray(['first' => 3.0, 'second' => 4.5, 'score' => 3.75, 'complete' => true])
            ->and($ana['cells']['Lengua Castellana'])->toMatchArray(['first' => 4.0, 'second' => 2.2, 'score' => 3.1, 'failed' => false]);
    });

    it('quien no cursó el primer semestre va marcado, sin puesto y fuera de las cuentas', function () {
        $consolidado = ($this->consolidado)('definitiva', true);
        $carla = ($this->row)($consolidado, $this->carla);

        expect($carla)->toMatchArray(['took_previous' => false, 'rank' => null])
            ->and(($this->row)($consolidado, $this->ana)['rank'])->not->toBeNull()
            ->and($consolidado['summary'])->toMatchArray(['students' => 3, 'without_previous' => 1, 'graded' => 2])
            ->and($consolidado['stats']['Matemáticas']['students'])->toBe(2);
    });

    it('cuenta a quienes cursaron el primer semestre y no siguieron', function () {
        $consolidado = ($this->consolidado)('definitiva', true);

        expect($consolidado['summary']['dropped'])->toBe(1)
            ->and($consolidado['dropped'])->toHaveCount(1)
            ->and($consolidado['dropped'][0])->toMatchArray(['name' => 'DUARTE DIANA', 'average' => 1.5, 'failed' => 2, 'group' => '3A-1'])
            // Por grupo no se puede: los grupos de 3A no son los de 3B.
            ->and(($this->consolidado)('definitiva', true, $this->g1->id)['dropped'])->toBe([]);
    });

    it('es definitivo solo con los dos semestres cerrados', function () {
        $this->cycle->closeModule(1);

        expect(($this->consolidado)('definitiva', true)['status']['detail'])->toBe('El Módulo 2 de Ciclo 3B sigue abierto');

        $this->cycle->close();

        expect(($this->consolidado)('definitiva', true)['status'])->toMatchArray([
            'definitive' => false,
            'detail' => 'Ciclo 3A (2026-1) todavía tiene módulos abiertos',
        ]);

        $this->previous->close();

        expect(($this->consolidado)('definitiva', true)['status']['definitive'])->toBeTrue();
    });

    it('solo el segundo semestre deja ver los módulos', function () {
        expect(($this->consolidado)('m1', false))->toMatchArray(['year' => false, 'view' => 'm1'])
            ->and(($this->consolidado)('m1', true))->toMatchArray(['year' => true, 'view' => 'definitiva']);
    });

    it('el primer semestre avisa que el año sigue en el segundo', function () {
        $consolidado = app(ConsolidadoService::class)->forCycle($this->previous, 'definitiva', true);

        expect($consolidado['year'])->toBeFalse()
            ->and($consolidado['continuesIn']->id)->toBe($this->cycle->id);
    });

    it('todo el semestre lee el año en 3B, salvo por módulo', function () {
        expect(app(ConsolidadoService::class)->forTerm(2026, 2)[0]['year'])->toBeTrue()
            ->and(app(ConsolidadoService::class)->forTerm(2026, 2, 'm1')[0]['year'])->toBeFalse();
    });

    it('la pantalla abre en el año y deja pasar al semestre', function () {
        Livewire::actingAs($this->admin)
            ->test(Consolidados::class)
            ->assertSee('Año completo (3A + 3B)')
            ->assertSee('sin 3A')
            ->assertSee('1 estudiante cursó 3A y no siguió en 3B')
            ->assertViewHas('consolidado', fn (array $c) => $c['year'])
            ->assertViewHas('downloadParams', [])
            ->set('span', 'semestre')
            ->set('view', 'm1')
            ->assertViewHas('consolidado', fn (array $c) => ! $c['year'] && $c['view'] === 'm1')
            ->assertViewHas('downloadParams', ['vista' => 'm1', 'alcance' => 'semestre'])
            ->set('span', 'anio')
            ->assertSet('view', 'definitiva');
    });

    it('3A lleva al consolidado del año en 3B', function () {
        Livewire::actingAs($this->admin)
            ->withQueryParams(['ciclo' => (string) $this->previous->id])
            ->test(Consolidados::class)
            ->assertSee('Ver el año en Ciclo 3B')
            ->set('cycleId', (string) $this->cycle->id)
            ->assertSet('semester', '2')
            ->assertViewHas('consolidado', fn (array $c) => $c['year']);
    });

    it('el Excel del año trae quién cursó 3A y la hoja de los que no siguieron', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'xlsx']))
            ->assertOk()
            ->assertDownload('consolidado-ciclo-3b-2026-2-anual.xlsx');

        $path = $response->baseResponse->getFile()->getPathname();
        $sheets = consolidadoSheets($path);
        unlink($path);

        expect(array_keys($sheets))->toBe(['Consolidado', 'Detalle por semestre', 'No siguieron en 3B'])
            ->and($sheets['Consolidado'])->toContain('Cursó 3A', 'Definitivas del año', '<v>3.75</v>')
            ->not->toContain('DUARTE DIANA')
            ->and($sheets['No siguieron en 3B'])->toContain('DUARTE DIANA', '<v>1.5</v>');

        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'xlsx', 'alcance' => 'semestre']))
            ->assertOk()
            ->assertDownload('consolidado-ciclo-3b-2026-2.xlsx');

        $this->actingAs($this->admin)
            ->get(route('admin.consolidados.download', ['cycle' => $this->cycle->id, 'formato' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
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

    it('un ciclo de un semestre no ofrece el año', function () {
        Livewire::actingAs($this->admin)
            ->test(Consolidados::class)
            ->assertDontSee('Año completo')
            ->assertViewHas('consolidado', fn (array $c) => ! $c['year'] && $c['yearParts'] === null && $c['continuesIn'] === null);
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
