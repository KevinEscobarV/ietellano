<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\Student;
use App\Services\CertificatePdfExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateDownloadController extends Controller
{
    public function cycle(Cycle $cycle, Request $request, CertificatePdfExporter $exporter): BinaryFileResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $zipPath = $exporter->zipForCycle($cycle, $request->boolean('both'));

        abort_if($zipPath === null, 404, __('El ciclo no tiene estudiantes matriculados.'));

        return response()
            ->download($zipPath, $exporter->zipName($cycle), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function student(Cycle $cycle, Student $student, Request $request, CertificatePdfExporter $exporter): Response
    {
        return $exporter->pdfForStudent($cycle, $student, $request->boolean('both'))->download($exporter->fileName($student));
    }
}
