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
     * La tabla del ciclo y una hoja con la nota de cada módulo (o de cada
     * semestre) por materia. Devuelve la ruta de un archivo temporal.
     *
     * @param  array<string, mixed>  $consolidado
     */
    public function cycleWorkbook(array $consolidado): string
    {
        $book = new XlsxWriter;

        $this->consolidadoSheet($book, $consolidado, __('Consolidado'));
        $this->detailSheet($book, $consolidado, $consolidado['both'] ? __('Detalle por semestre') : __('Detalle por módulo'));

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
            $consolidado['both'] => 'dos semestres',
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

        $widths = [5, 36, 14];

        if ($c['showGroup']) {
            $widths[] = 13;
        }

        $widths = [...$widths, ...array_fill(0, count($c['columns']), 12), 10, 12, 8, 8, 8];

        $book->addSheet($name, $rows, [
            'widths' => $widths,
            'heights' => [$headerRow => 32],
            'freeze' => [2, $headerRow],
            'filter' => 'A'.$headerRow.':'.XlsxWriter::column(count($header) - 1).max($lastDataRow, $headerRow),
            'landscape' => true,
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

        $book->addSheet($name, $rows, [
            'widths' => [5, 36, ...($c['showGroup'] ? [13] : []), ...array_fill(0, count($c['columns']) * 3, 7)],
            'freeze' => [2, $top + 1],
            'merge' => $merge,
            'landscape' => true,
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
            __('Ciclo'), __('Estudiantes'), __('Promedio'), __('Desempeño'), __('Aprueban todo'),
            __('Pierden 1'), __('Pierden 2'), __('Pierden 3 o más'), __('Notas que faltan'), __('Materia con más pérdidas'), __('Estado'),
        ];
        $headerRow = count($rows) + 1;
        $rows[] = array_map(fn (string $text) => XlsxWriter::cell($text, 'header'), $header);

        foreach ($consolidados as $c) {
            $summary = $c['summary'];
            $hardest = $summary['hardest'][0] ?? null;

            $rows[] = [
                XlsxWriter::cell($c['cycle']->name, 'bold'),
                XlsxWriter::cell($summary['students'], 'integer'),
                XlsxWriter::cell($summary['average'], $summary['average'] !== null && $summary['average'] < BoletinService::PASSING_SCORE ? 'score-fail' : 'bold-score'),
                XlsxWriter::cell($summary['performance'], 'center'),
                XlsxWriter::cell($summary['all_passed'], 'integer'),
                XlsxWriter::cell($summary['failing'][1], $summary['failing'][1] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($summary['failing'][2], $summary['failing'][2] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($summary['failing'][3], $summary['failing'][3] > 0 ? 'integer-fail' : 'integer'),
                XlsxWriter::cell($summary['pending_cells'], 'integer'),
                XlsxWriter::cell($hardest ? __(':name (:failed de :graded)', ['name' => $hardest['name'], 'failed' => $hardest['failed'], 'graded' => $hardest['graded']]) : '—', 'text'),
                XlsxWriter::cell($c['status']['label'], $c['status']['definitive'] ? 'center' : 'seal-provisional'),
            ];
        }

        $book->addSheet(__('Resumen'), $rows, [
            'widths' => [16, 12, 11, 12, 13, 11, 11, 13, 13, 34, 13],
            'heights' => [$headerRow => 32],
            'freeze' => [1, $headerRow],
            'landscape' => true,
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

        $summary = trans_choice('{1}:count estudiante|[0,*]:count estudiantes', $stats['students'], ['count' => $stats['students']])
            .' · '.__('promedio :avg', ['avg' => $this->number($stats['final'])])
            .' · '.__('aprueban :n', ['n' => $stats['passed']])
            .' · '.__('pierden :n', ['n' => $stats['failed']])
            .($stats['pending'] > 0 ? ' · '.__('con nota incompleta :n', ['n' => $stats['pending']]) : '');

        $rows = $this->heading(__('Consolidado de :subject', ['subject' => $scope]), $data['status'], $summary);
        array_splice($rows, 2, 0, [[XlsxWriter::cell(__('Docente: :name', ['name' => $data['teacher']->name]), 'muted')]]);

        $headerRow = count($rows) + 1;
        $rows[] = array_map(
            fn (string $text) => XlsxWriter::cell($text, 'header'),
            [__('N°'), __('Estudiante'), __('Documento'), __('Módulo 1'), __('Módulo 2'), __('Definitiva'), __('Desempeño'), __('Fallas')],
        );

        foreach ($section['rows'] as $index => $row) {
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($row['name'], 'text'),
                XlsxWriter::cell($row['document'], 'center'),
                $this->plainScore($row['m1']),
                $this->plainScore($row['m2']),
                $this->scoreCell(['score' => $row['final'], 'complete' => $row['complete'], 'failed' => $row['failed']]),
                XlsxWriter::cell($row['performance'], 'center'),
                XlsxWriter::cell($row['absences'], $row['lost_by_absence'] ? 'integer-fail' : 'integer'),
            ];
        }

        $lastDataRow = count($rows);
        $rows[] = [];
        $rows[] = [
            null,
            XlsxWriter::cell(__('Promedio'), 'bold'),
            null,
            XlsxWriter::cell($stats['m1'], 'bold-score'),
            XlsxWriter::cell($stats['m2'], 'bold-score'),
            XlsxWriter::cell($stats['final'], 'bold-score'),
        ];
        $rows[] = [];
        $rows[] = [XlsxWriter::cell($this->legend(), 'muted')];

        $book->addSheet(trim($section['subject'].' '.($section['group'] ?? '')), $rows, [
            'widths' => [5, 36, 14, 11, 11, 11, 12, 8],
            'heights' => [$headerRow => 20],
            'freeze' => [2, $headerRow],
            'filter' => 'A'.$headerRow.':H'.max($lastDataRow, $headerRow),
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

        return implode(' · ', $parts);
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
