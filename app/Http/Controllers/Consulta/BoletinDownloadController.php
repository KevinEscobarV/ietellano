<?php

namespace App\Http\Controllers\Consulta;

use App\Http\Controllers\Controller;
use App\Livewire\Consulta\Boletin;
use App\Models\Cycle;
use App\Models\Student;
use App\Services\BoletinPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El PDF del boletín para el propio estudiante. La URL solo dice qué semestre
 * quiere: de quién es el boletín lo decide la sesión, así que cambiar el
 * número de la dirección no abre el de nadie más.
 */
class BoletinDownloadController extends Controller
{
    public function __invoke(Cycle $cycle, Request $request, BoletinPdfExporter $exporter): Response
    {
        $studentId = $request->session()->get(Boletin::SESSION_KEY);

        abort_if($studentId === null, 403, __('Tu consulta expiró. Vuelve a ingresar tu documento.'));

        $student = Student::findOrFail($studentId);

        abort_unless(
            $student->enrollments()->where('cycle_id', $cycle->id)->exists(),
            404,
            __('No estás matriculado en ese semestre.'),
        );

        return $exporter
            ->pdfForStudent($cycle, $student, $request->boolean('both'))
            ->download($exporter->fileName($student));
    }
}
