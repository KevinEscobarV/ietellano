<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\Student;
use App\Services\BoletinPdfExporter;
use App\Services\BoletinService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoletinDownloadController extends Controller
{
    public function cycle(Cycle $cycle, Request $request, BoletinPdfExporter $exporter): BinaryFileResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $span = BoletinService::span(BoletinPdfExporter::spanFrom($request), $cycle);
        $zipPath = $exporter->zipForCycle($cycle, $span);

        abort_if($zipPath === null, 404, __('El ciclo no tiene estudiantes matriculados.'));

        return response()
            ->download($zipPath, $exporter->zipName($cycle, $span), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function student(Cycle $cycle, Student $student, Request $request, BoletinPdfExporter $exporter): Response
    {
        $span = BoletinService::span(BoletinPdfExporter::spanFrom($request), $cycle);

        return $exporter->pdfForStudent($cycle, $student, $span)->download($exporter->fileName($student, $span));
    }
}
