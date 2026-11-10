<?php

namespace App\Services;

use App\Support\ResizesInstitutionLogo;
use App\Support\XlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

/**
 * Los consolidados en Excel (para filtrar y ordenar) y en PDF (para imprimir
 * o archivar). Los dos llevan el sello de provisional o definitivo.
 */
class ConsolidadoExporter
{
    use ResizesInstitutionLogo;

    public const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Los estilos de los renglones que son solo texto, sin tabla.
     */
    private const TEXT_ROWS = ['title', 'subtitle', 'muted', 'seal-provisional', 'seal-definitive'];

    /**
     * La tabla del ciclo y una hoja con la nota de cada módulo (o de cada
     * semestre) por materia. En el año, una hoja más con quienes cursaron el
     * primer semestre y no siguieron. Devuelve la ruta de un archivo temporal.
     *
     * @param  array<string, mixed>  $consolidado
     */
    public function cycleWorkbook(array $consolidado): string
    {
        $book = new XlsxWriter;

        $this->consolidadoSheet($book, $consolidado, __('Consolidado'));
        $this->detailSheet($book, $consolidado, $consolidado['year'] ? __('Detalle por semestre') : __('Detalle por módulo'));

        if ($consolidado['dropped'] !== []) {
            $this->droppedSheet($book, $consolidado);
        }

        return $this->save($book);
    }

    /**
     * Una hoja de resumen con un renglón por ciclo y después la tabla de cada
     * ciclo en su propia hoja.
     *
     * @param  list<array<string, mixed>>  $consolidados
     */
    public function termWorkbook(array $consolidados, string $termLabel): string
    {
        $book = new XlsxWriter;

        $this->termSummarySheet($book, $consolidados, $termLabel);

        foreach ($consolidados as $consolidado) {
            $this->consolidadoSheet($book, $consolidado, $consolidado['cycle']->name);
        }

        return $this->save($book);
    }

    /**
     * Una hoja por cada materia y grupo del docente.
     *
     * @param  array<string, mixed>  $data  lo de ConsolidadoService::forTeacher()
     */
    public function teacherWorkbook(array $data): string
    {
        $book = new XlsxWriter;

        foreach ($data['sections'] as $section) {
            $this->teacherSheet($book, $data, $section);
        }

        return $this->save($book);
    }

    /**
     * @param  array<string, mixed>  $consolidado
     */
    public function cyclePdf(array $consolidado): DomPdf
    {
        return $this->pdf([$consolidado], null);
    }

    /**
     * @param  list<array<string, mixed>>  $consolidados
     */
    public function termPdf(array $consolidados, string $termLabel): DomPdf
    {
        return $this->pdf($consolidados, $termLabel);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function teacherPdf(array $data): DomPdf
    {
        return Pdf::loadView('pdf.consolidado-docente', [
            'data' => $data,
            'logo' => $this->institutionLogo(),
            'passingScore' => BoletinService::PASSING_SCORE,
        ])->setPaper('letter');
    }

    /**
     * @param  array<string, mixed>  $consolidado
     */
    public function cycleFileName(array $consolidado, string $extension): string
    {
        $parts = ['consolidado', $consolidado['cycle']->name, $consolidado['cycle']->year.'-'.$consolidado['cycle']->semester];

        if ($consolidado['group']) {
            $parts[] = $consolidado['group']->name;
        }

        $parts[] = match (true) {
            $consolidado['year'] => 'anual',
            $consolidado['view'] === 'm1' => 'modulo 1',
            $consolidado['view'] === 'm2' => 'modulo 2',
            default => '',
        };

        return Str::slug(implode(' ', $parts)).'.'.$extension;
    }

    public function termFileName(int $year, int $semester, string $view, string $extension): string
    {
        $suffix = match ($view) {
            'm1' => ' modulo 1',
            'm2' => ' modulo 2',
            default => '',
        };

        return Str::slug("consolidado {$year}-{$semester}{$suffix}").'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function teacherFileName(array $data, string $extension): string
    {
        $cycle = $data['cycle'];

        return Str::slug("consolidado {$data['teacher']->name} {$cycle->name} {$cycle->year}-{$cycle->semester}").'.'.$extension;
    }

    /**
     * @param  list<array<string, mixed>>  $consolidados
     */
    private function pdf(array $consolidados, ?string $termLabel): DomPdf
    {
        return Pdf::loadView('pdf.consolidado', [
            'consolidados' => $consolidados,
            'termLabel' => $termLabel,
            'logo' => $this->institutionLogo(),
            'passingScore' => BoletinService::PASSING_SCORE,
        ])->setPaper('letter', 'landscape');
    }

    /**
     * Agrega la hoja con cada renglón de texto (título, sello, resumen, notas)
     * combinado a lo ancho de la tabla y con el alto que necesita. Si no, el
     * texto largo se sale de la tabla y al imprimir queda cortado en el
     * margen.
     *
     * @param  list<list<mixed>>  $rows
     * @param  list<float|int>  $widths
     * @param  array<string, mixed>  $options
     */
    private function sheet(XlsxWriter $book, string $name, array $rows, array $widths, array $options): void
    {
        $last = XlsxWriter::column(count($widths) - 1);
        // El ancho de Excel se mide en ceros de Calibri 11; un texto normal
        // mete algo más de un carácter por unidad.
        $perLine = max(1, array_sum($widths) * 1.1);
        $merge = $options['merge'] ?? [];
        $heights = $options['heights'] ?? [];

        foreach (array_values($rows) as $index => $cells) {
            $cell = $cells[0] ?? null;

            if (count($cells) !== 1 || ! is_array($cell) || ! in_array($cell['style'], self::TEXT_ROWS, true)) {
                continue;
            }

            $number = $index + 1;
            $merge[] = "A{$number}:{$last}{$number}";
            $lines = max(1, (int) ceil(mb_strlen((string) $cell['value']) / $perLine));

            if ($cell['style'] === 'title') {
                $heights[$number] ??= 20;
            } elseif ($lines > 1) {
                $heights[$number] ??= $lines * 15;
            }
        }

        $book->addSheet($name, $rows, [...$options, 'widths' => $widths, 'merge' => $merge, 'heights' => $heights]);
    }

    private function save(XlsxWriter $book): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'con');
        $book->save($path);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function consolidadoSheet(XlsxWriter $book, array $c, string $name): void
    {
        $header = [__('N°'), __('Estudiante'), __('Documento')];

        if ($c['showGroup']) {
            $header[] = __('Grupo');
        }

        // En el año, quién cursó el primer semestre; se filtra con un clic.
        if ($c['year']) {
            $header[] = __('Cursó :level', ['level' => $c['yearParts']['previous']]);
        }

        $fixed = count($header);

        foreach ($c['columns'] as $column) {
            $header[] = $column['name'];
        }

        array_push($header, __('Promedio'), __('Desempeño'), __('Puesto'), __('Pierde'), __('Faltan'));

        $rows = $this->heading($c['title'].' · '.$c['scope'], $c['status'], $this->summaryLine($c['summary']));
        $headerRow = count($rows) + 1;
        $rows[] = array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $header);

        foreach ($c['rows'] as $index => $row) {
            $line = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($row['name'], 'text'),
                XlsxWriter::cell($row['document'], 'center'),
            ];

            if ($c['showGroup']) {
                $line[] = XlsxWriter::cell($row['group'], 'center');
            }

            if ($c['year']) {
                $line[] = $row['took_previous'] ? XlsxWriter::cell(__('Sí'), 'center') : XlsxWriter::cell(__('No'), 'integer-fail');
            }

            foreach ($c['columns'] as $column) {
                $line[] = $this->scoreCell($row['cells'][$column['name']] ?? null);
            }

            array_push(
                $line,
                XlsxWriter::cell($row['average'], 'bold-score'),
                XlsxWriter::cell($row['performance'], 'center'),
                XlsxWriter::cell($row['rank'], 'integer'),
                XlsxWriter::cell($row['failed'], $row['failed'] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($row['pending'], 'integer'),
            );

            $rows[] = $line;
        }

        $lastDataRow = count($rows);
        $rows[] = [];

        foreach ([[__('Promedio'), 'average'], [__('Aprueban'), 'passed'], [__('Pierden'), 'failed'], [__('Faltan'), 'pending']] as [$label, $key]) {
            $line = array_fill(0, $fixed, null);
            $line[1] = XlsxWriter::cell($label, 'bold');

            foreach ($c['columns'] as $column) {
                $value = $c['stats'][$column['name']][$key];
                $line[] = XlsxWriter::cell($value, match (true) {
                    $key === 'average' => 'bold-score',
                    $key === 'failed' && $value > 0 => 'integer-fail',
                    default => 'integer',
                });
            }

            if ($key === 'average') {
                $line[] = XlsxWriter::cell($c['summary']['average'], 'bold-score');
            }

            $rows[] = $line;
        }

        $rows[] = [];
        $rows[] = [XlsxWriter::cell($this->legend(), 'muted')];

        if ($c['year']) {
            $rows[] = [XlsxWriter::cell($this->yearLegend($c), 'muted')];
        }

        $widths = [5, 36, 14];

        if ($c['showGroup']) {
            $widths[] = 13;
        }

        if ($c['year']) {
            $widths[] = 10;
        }

        $widths = [...$widths, ...array_fill(0, count($c['columns']), 12), 10, 12, 8, 8, 8];

        $this->sheet($book, $name, $rows, $widths, [
            'heights' => [$headerRow => 32],
            'freeze' => [2, $headerRow],
            'filter' => 'A'.$headerRow.':'.XlsxWriter::column(count($header) - 1).max($lastDataRow, $headerRow),
            'landscape' => true,
            'repeat' => [$headerRow, $headerRow],
        ]);
    }

    /**
     * Las tres notas de cada materia: M1, M2 y definitiva (o semestre 1,
     * semestre 2 y final).
     *
     * @param  array<string, mixed>  $c
     */
    private function detailSheet(XlsxWriter $book, array $c, string $name): void
    {
        $rows = $this->heading($c['title'].' · '.$c['scope'], $c['status'], null);
        $top = count($rows) + 1;

        $first = [XlsxWriter::cell(__('N°'), 'header'), XlsxWriter::cell(__('Estudiante'), 'header')];
        $second = [XlsxWriter::cell(null, 'header'), XlsxWriter::cell(null, 'header')];

        if ($c['showGroup']) {
            $first[] = XlsxWriter::cell(__('Grupo'), 'header');
            $second[] = XlsxWriter::cell(null, 'header');
        }

        $fixed = count($first);
        $merge = [];

        for ($column = 0; $column < $fixed; $column++) {
            $letter = XlsxWriter::column($column);
            $merge[] = "{$letter}{$top}:{$letter}".($top + 1);
        }

        foreach ($c['columns'] as $index => $column) {
            $start = $fixed + $index * 3;
            $merge[] = XlsxWriter::column($start).$top.':'.XlsxWriter::column($start + 2).$top;

            array_push($first, XlsxWriter::cell($column['name'], 'header'), XlsxWriter::cell(null, 'header'), XlsxWriter::cell(null, 'header'));

            foreach ($c['detailLabels'] as $label) {
                $second[] = XlsxWriter::cell($label, 'header');
            }
        }

        $rows[] = $first;
        $rows[] = $second;

        foreach ($c['rows'] as $index => $row) {
            $line = [XlsxWriter::cell($index + 1, 'integer'), XlsxWriter::cell($row['name'], 'text')];

            if ($c['showGroup']) {
                $line[] = XlsxWriter::cell($row['group'], 'center');
            }

            foreach ($c['columns'] as $column) {
                $cell = $row['cells'][$column['name']] ?? null;

                array_push(
                    $line,
                    $this->plainScore($cell['first'] ?? null),
                    $this->plainScore($cell['second'] ?? null),
                    $this->plainScore($cell['final'] ?? null, bold: true),
                );
            }

            $rows[] = $line;
        }

        $rows[] = [];
        $rows[] = [XlsxWriter::cell(__('En rojo, las notas por debajo de :score.', ['score' => number_format(BoletinService::PASSING_SCORE, 1)]), 'muted')];

        $this->sheet($book, $name, $rows, [5, 36, ...($c['showGroup'] ? [13] : []), ...array_fill(0, count($c['columns']) * 3, 7)], [
            'freeze' => [2, $top + 1],
            'merge' => $merge,
            'landscape' => true,
            'repeat' => [$top, $top + 1],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $consolidados
     */
    private function termSummarySheet(XlsxWriter $book, array $consolidados, string $termLabel): void
    {
        $title = ($consolidados[0]['title'] ?? __('Definitivas')).' · '.$termLabel;

        $rows = [
            [XlsxWriter::cell(config('app.institution'), 'title')],
            [XlsxWriter::cell($title, 'subtitle')],
            [XlsxWriter::cell(__('Un renglón por ciclo. La tabla de cada uno está en su propia hoja.'), 'muted')],
            [],
        ];

        $header = [
            __('Ciclo'), __('Se lee'), __('Estudiantes'), __('Promedio'), __('Desempeño'), __('Aprueban todo'),
            __('Pierden 1'), __('Pierden 2'), __('Pierden 3 o más'), __('Notas que faltan'), __('Materia con más pérdidas'),
            __('No siguieron del primer semestre'), __('Estado'),
        ];
        $headerRow = count($rows) + 1;
        $rows[] = array_map(fn (string $text) => XlsxWriter::cell($text, 'header'), $header);

        foreach ($consolidados as $c) {
            $summary = $c['summary'];
            $hardest = $summary['hardest'][0] ?? null;

            $rows[] = [
                XlsxWriter::cell($c['cycle']->name, 'bold'),
                XlsxWriter::cell($this->spanLabel($c), 'center'),
                XlsxWriter::cell($summary['students'], 'integer'),
                XlsxWriter::cell($summary['average'], $summary['average'] !== null && $summary['average'] < BoletinService::PASSING_SCORE ? 'score-fail' : 'bold-score'),
                XlsxWriter::cell($summary['performance'], 'center'),
                XlsxWriter::cell($summary['all_passed'], 'integer'),
                XlsxWriter::cell($summary['failing'][1], $summary['failing'][1] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($summary['failing'][2], $summary['failing'][2] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($summary['failing'][3], $summary['failing'][3] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($summary['pending_cells'], 'integer'),
                XlsxWriter::cell($hardest ? __(':name (:failed de :graded)', ['name' => $hardest['name'], 'failed' => $hardest['failed'], 'graded' => $hardest['graded']]) : '—', 'text'),
                XlsxWriter::cell($c['year'] ? $summary['dropped'] : null, 'integer'),
                XlsxWriter::cell($c['status']['label'], $c['status']['definitive'] ? 'center' : 'seal-provisional'),
            ];
        }

        $rows[] = [];
        $rows[] = [XlsxWriter::cell(__('En los ciclos de un año (3 y 4), el segundo semestre se lee con el año completo: la nota de cada materia es el promedio de los dos semestres.'), 'muted')];

        $this->sheet($book, __('Resumen'), $rows, [16, 22, 12, 11, 12, 13, 11, 11, 13, 13, 34, 16, 13], [
            'heights' => [$headerRow => 32],
            'freeze' => [1, $headerRow],
            'landscape' => true,
            'repeat' => [$headerRow, $headerRow],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $section
     */
    private function teacherSheet(XlsxWriter $book, array $data, array $section): void
    {
        $scope = $section['subject'].' · '.$data['scope'].($section['group'] ? ' · '.$section['group'] : '');
        $stats = $section['stats'];

        $parts = $data['yearParts'];
        $year = $section['year'];

        $summary = trans_choice('{1}:count estudiante|[0,*]:count estudiantes', $stats['students'], ['count' => $stats['students']])
            .' · '.__('promedio :avg', ['avg' => $this->number($stats['final'])])
            .' · '.__('aprueban :n', ['n' => $stats['passed']])
            .' · '.__('pierden :n', ['n' => $stats['failed']])
            .($stats['pending'] > 0 ? ' · '.__('con nota incompleta :n', ['n' => $stats['pending']]) : '')
            .($year ? ' · '.__('en el año: promedio :avg, pierden :n', ['avg' => $this->number($stats['year']), 'n' => $stats['year_failed']]) : '');

        $rows = $this->heading(__('Consolidado de :subject', ['subject' => $scope]), $data['status'], $summary);
        array_splice($rows, 2, 0, [[XlsxWriter::cell(__('Docente: :name', ['name' => $data['teacher']->name]), 'muted')]]);

        // En orden: el primer semestre, los dos módulos de este, su definitiva y el año.
        $header = $year
            ? [__('N°'), __('Estudiante'), __('Documento'), __('Def. :level', ['level' => $parts['previous']]), __('Módulo 1'), __('Módulo 2'), __('Def. :level', ['level' => $parts['current']]), __('Año'), __('Desempeño del año'), __('Fallas')]
            : [__('N°'), __('Estudiante'), __('Documento'), __('Módulo 1'), __('Módulo 2'), __('Definitiva'), __('Desempeño'), __('Fallas')];

        $headerRow = count($rows) + 1;
        $rows[] = array_map(fn (string $text) => XlsxWriter::cell($text, 'header'), $header);

        foreach ($section['rows'] as $index => $row) {
            $line = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($row['name'], 'text'),
                XlsxWriter::cell($row['document'], 'center'),
            ];

            if ($year) {
                $line[] = $row['took_previous']
                    ? $this->plainScore($row['previous'])
                    : XlsxWriter::cell(__('No cursó'), 'score-pending');
            }

            array_push(
                $line,
                $this->plainScore($row['m1']),
                $this->plainScore($row['m2']),
                $this->scoreCell(['score' => $row['final'], 'complete' => $row['complete'], 'failed' => $row['failed']]),
            );

            if ($year) {
                $line[] = $row['took_previous']
                    ? $this->scoreCell(['score' => $row['year'], 'complete' => $row['year_complete'], 'failed' => $row['year_failed']])
                    : XlsxWriter::cell(null, 'center');
            }

            $line[] = XlsxWriter::cell($row['performance'], 'center');
            $line[] = XlsxWriter::cell($row['absences'], $row['lost_by_absence'] ? 'integer-fail' : 'integer');

            $rows[] = $line;
        }

        $lastDataRow = count($rows);
        $rows[] = [];
        $rows[] = [
            null,
            XlsxWriter::cell(__('Promedio'), 'bold'),
            null,
            ...($year ? [XlsxWriter::cell($stats['previous'], 'bold-score')] : []),
            XlsxWriter::cell($stats['m1'], 'bold-score'),
            XlsxWriter::cell($stats['m2'], 'bold-score'),
            XlsxWriter::cell($stats['final'], 'bold-score'),
            ...($year ? [XlsxWriter::cell($stats['year'], 'bold-score')] : []),
        ];
        $rows[] = [];
        $rows[] = [XlsxWriter::cell($this->legend(), 'muted')];

        if ($year) {
            $rows[] = [XlsxWriter::cell(__('Año: promedio de la definitiva de :first y la de :second. Se pierde por debajo de :score.', [
                'first' => $parts['previous'],
                'second' => $parts['current'],
                'score' => number_format(BoletinService::PASSING_SCORE, 1),
            ]), 'muted')];
        }

        // Vertical y encogida al ancho de la página: con el año son diez
        // columnas y, sin esto, las últimas se iban a una segunda hoja.
        $this->sheet($book, trim($section['subject'].' '.($section['group'] ?? '')), $rows, $year ? [5, 34, 13, 9, 9, 9, 9, 9, 12, 7] : [5, 36, 14, 11, 11, 11, 12, 8], [
            'heights' => [$headerRow => $year ? 32 : 20],
            'freeze' => [2, $headerRow],
            'filter' => 'A'.$headerRow.':'.XlsxWriter::column(count($header) - 1).max($lastDataRow, $headerRow),
            'fit' => true,
            'repeat' => [$headerRow, $headerRow],
        ]);
    }

    /**
     * Institución, título, sello y resumen, y una fila en blanco.
     *
     * @param  array{definitive: bool, label: string, detail: string}  $status
     * @return list<list<mixed>>
     */
    private function heading(string $title, array $status, ?string $summary): array
    {
        $rows = [
            [XlsxWriter::cell(config('app.institution'), 'title')],
            [XlsxWriter::cell($title, 'subtitle')],
            [XlsxWriter::cell(mb_strtoupper($status['label']).' · '.$status['detail'], $status['definitive'] ? 'seal-definitive' : 'seal-provisional')],
        ];

        if ($summary !== null) {
            $rows[] = [XlsxWriter::cell($summary, 'muted')];
        }

        $rows[] = [];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function summaryLine(array $summary): string
    {
        $parts = [
            trans_choice('{1}:count estudiante|[0,*]:count estudiantes', $summary['students'], ['count' => $summary['students']]),
            __('promedio :avg', ['avg' => $this->number($summary['average'])]).($summary['performance'] ? " ({$summary['performance']})" : ''),
            __('aprueban todo :n', ['n' => $summary['all_passed']]),
            __('pierden 1: :n', ['n' => $summary['failing'][1]]),
            __('pierden 2: :n', ['n' => $summary['failing'][2]]),
            __('pierden 3 o más: :n', ['n' => $summary['failing'][3]]),
        ];

        if ($summary['pending_cells'] > 0) {
            $parts[] = trans_choice('{1}falta :count nota|[0,*]faltan :count notas', $summary['pending_cells'], ['count' => $summary['pending_cells']]);
        }

        if ($summary['without_previous'] > 0) {
            $parts[] = __('sin el primer semestre: :n', ['n' => $summary['without_previous']]);
        }

        if ($summary['dropped'] > 0) {
            $parts[] = __('no siguieron del primer semestre: :n', ['n' => $summary['dropped']]);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function yearLegend(array $c): string
    {
        return __('Nota del año: promedio de la definitiva de :first y la de :second. Quienes no cursaron :first van sin puesto y fuera de las cuentas del ciclo.', [
            'first' => $c['yearParts']['previous'],
            'second' => $c['yearParts']['current'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function spanLabel(array $c): string
    {
        return $c['year']
            ? __('Año (:first + :second)', ['first' => $c['yearParts']['previous'], 'second' => $c['yearParts']['current']])
            : __('Semestre');
    }

    /**
     * Quienes cursaron el primer semestre y no aparecen en el segundo.
     *
     * @param  array<string, mixed>  $c
     */
    private function droppedSheet(XlsxWriter $book, array $c): void
    {
        $first = $c['yearParts']['previous'];
        $second = $c['yearParts']['current'];

        $rows = [
            [XlsxWriter::cell(config('app.institution'), 'title')],
            [XlsxWriter::cell(__('Cursaron :first y no siguieron en :second', ['first' => $first, 'second' => $second]).' · '.$c['scope'], 'subtitle')],
            [XlsxWriter::cell(trans_choice('{1}1 estudiante|[0,*]:count estudiantes', count($c['dropped']), ['count' => count($c['dropped'])]), 'muted')],
            [],
        ];

        $headerRow = count($rows) + 1;
        $rows[] = array_map(
            fn (string $text) => XlsxWriter::cell($text, 'header'),
            [__('N°'), __('Estudiante'), __('Documento'), __('Grupo en :level', ['level' => $first]), __('Promedio en :level', ['level' => $first]), __('Perdió en :level', ['level' => $first])],
        );

        foreach ($c['dropped'] as $index => $student) {
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['name'], 'text'),
                XlsxWriter::cell($student['document'], 'center'),
                XlsxWriter::cell($student['group'], 'center'),
                $this->plainScore($student['average'], bold: true),
                XlsxWriter::cell($student['failed'], $student['failed'] > 0 ? 'integer-fail' : 'integer'),
            ];
        }

        $this->sheet($book, __('No siguieron en :level', ['level' => $second]), $rows, [5, 36, 14, 14, 14, 12], [
            'heights' => [$headerRow => 32],
            'freeze' => [2, $headerRow],
            'filter' => 'A'.$headerRow.':F'.(count($rows)),
            'fit' => true,
            'repeat' => [$headerRow, $headerRow],
        ]);
    }

    private function legend(): string
    {
        return __('En rojo, las notas por debajo de :score. En gris y cursiva, las que todavía no están completas porque falta la nota de algún módulo.', [
            'score' => number_format(BoletinService::PASSING_SCORE, 1),
        ]);
    }

    /**
     * @param  array{score: ?float, complete: bool, failed: bool}|null  $cell
     * @return array{value: ?float, style: string}
     */
    private function scoreCell(?array $cell): array
    {
        if ($cell === null) {
            // La materia no le toca a este estudiante.
            return XlsxWriter::cell(null, 'center');
        }

        $style = match (true) {
            ! $cell['complete'] && $cell['failed'] => 'score-pending-fail',
            ! $cell['complete'] => 'score-pending',
            $cell['failed'] => 'score-fail',
            default => 'score',
        };

        return XlsxWriter::cell($cell['score'], $style);
    }

    /**
     * @return array{value: ?float, style: string}
     */
    private function plainScore(?float $score, bool $bold = false): array
    {
        return XlsxWriter::cell($score, match (true) {
            $score !== null && $score < BoletinService::PASSING_SCORE => 'score-fail',
            $bold => 'bold-score',
            default => 'score',
        });
    }

    private function number(?float $value): string
    {
        return $value !== null ? number_format($value, 2) : '—';
    }
}
