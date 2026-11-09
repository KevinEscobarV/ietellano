<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Services\ConsolidadoExporter;
use App\Services\ConsolidadoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las descargas van por GET y no por una acción de Livewire, que mandaría el
 * archivo entero dentro de su respuesta JSON.
 */
class ConsolidadoDownloadController extends Controller
{
    public function cycle(Cycle $cycle, Request $request, ConsolidadoService $service, ConsolidadoExporter $exporter): Response|BinaryFileResponse
    {
        $format = $this->format($request);

        $consolidado = $service->forCycle(
            $cycle,
            (string) $request->query('vista', 'definitiva'),
            $request->boolean('dos'),
            $request->integer('grupo') ?: null,
            (string) $request->query('orden', 'nombre'),
        );

        if ($format === 'pdf') {
            return $exporter->cyclePdf($consolidado)->download($exporter->cycleFileName($consolidado, 'pdf'));
        }

        return $this->workbook($exporter->cycleWorkbook($consolidado), $exporter->cycleFileName($consolidado, 'xlsx'));
    }

    public function term(int $year, int $semester, Request $request, ConsolidadoService $service, ConsolidadoExporter $exporter): Response|BinaryFileResponse
    {
        $format = $this->format($request);
        $view = (string) $request->query('vista', 'definitiva');
        $view = in_array($view, ConsolidadoService::VIEWS, true) ? $view : 'definitiva';

        @set_time_limit(300);

        $consolidados = $service->forTerm($year, $semester, $view);

        abort_if($consolidados === [], 404, __('El semestre no tiene ciclos con estudiantes.'));

        $label = __(':year · Semestre :n', ['year' => $year, 'n' => $semester]);

        if ($format === 'pdf') {
            return $exporter->termPdf($consolidados, $label)->download($exporter->termFileName($year, $semester, $view, 'pdf'));
        }

        return $this->workbook($exporter->termWorkbook($consolidados, $label), $exporter->termFileName($year, $semester, $view, 'xlsx'));
    }

    private function format(Request $request): string
    {
        $format = (string) $request->query('formato', 'xlsx');

        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        return $format;
    }

    private function workbook(string $path, string $name): BinaryFileResponse
    {
        return response()
            ->download($path, $name, ['Content-Type' => ConsolidadoExporter::XLSX_MIME])
            ->deleteFileAfterSend(true);
    }
}
