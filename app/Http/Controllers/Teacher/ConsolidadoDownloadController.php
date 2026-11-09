<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Services\ConsolidadoExporter;
use App\Services\ConsolidadoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El consolidado del docente: solo sus materias y sus grupos del ciclo. Un
 * ciclo donde no dicta nada no existe para él.
 */
class ConsolidadoDownloadController extends Controller
{
    public function __invoke(Cycle $cycle, Request $request, ConsolidadoService $service, ConsolidadoExporter $exporter): Response|BinaryFileResponse
    {
        $format = (string) $request->query('formato', 'xlsx');

        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        // El middleware `teacher` ya garantizó que la cuenta tiene docente.
        $data = $service->forTeacher($request->user()->teacher, $cycle);

        abort_if($data['sections'] === [], 404);

        if ($format === 'pdf') {
            return $exporter->teacherPdf($data)->download($exporter->teacherFileName($data, 'pdf'));
        }

        return response()
            ->download($exporter->teacherWorkbook($data), $exporter->teacherFileName($data, 'xlsx'), ['Content-Type' => ConsolidadoExporter::XLSX_MIME])
            ->deleteFileAfterSend(true);
    }
}
