<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\Student;
use App\Support\ResizesInstitutionLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Los boletines en PDF. `$span` dice qué notas llevan: m1, m2, semestre o
 * anio (ver BoletinService::SPANS).
 */
class BoletinPdfExporter
{
    use ResizesInstitutionLogo;

    public function __construct(private BoletinService $service) {}

    /**
     * La lectura que pide una descarga: `notas=m1|m2|semestre|anio`. Los
     * enlaces viejos decían `both=1` para el año.
     */
    public static function spanFrom(Request $request): string
    {
        return (string) $request->query('notas', $request->boolean('both') ? 'anio' : 'semestre');
    }

    public function pdfForStudent(Cycle $cycle, Student $student, string $span = 'semestre'): DomPdf
    {
        return Pdf::loadView('pdf.boletin', [
            'boletin' => $this->service->forSpan($student, $cycle, $span),
            'passingScore' => BoletinService::PASSING_SCORE,
            'logo' => $this->institutionLogo(),
        ])->setPaper('letter');
    }

    /**
     * Build a ZIP with one boletín PDF per enrolled student. With the year,
     * only students enrolled in the previous cycle too are included. Returns
     * the temp file path, or null when there are none.
     */
    public function zipForCycle(Cycle $cycle, string $span = 'semestre'): ?string
    {
        $span = BoletinService::span($span, $cycle);
        $students = $this->students($cycle, $span === 'anio');

        if ($students->isEmpty()) {
            return null;
        }

        $logo = $this->institutionLogo();

        $zipPath = tempnam(sys_get_temp_dir(), 'bol').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($students as $student) {
            $pdf = Pdf::loadView('pdf.boletin', [
                'boletin' => $this->service->forSpan($student, $cycle, $span),
                'passingScore' => BoletinService::PASSING_SCORE,
                'logo' => $logo,
            ])->setPaper('letter');

            $zip->addFromString($this->fileName($student, $span), $pdf->output());

            unset($pdf);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(Cycle $cycle, bool $bothSemesters)
    {
        return Student::query()
            ->whereRelation('enrollments', 'cycle_id', $cycle->id)
            ->when($bothSemesters && $cycle->previous_cycle_id, fn ($query) => $query->whereRelation('enrollments', 'cycle_id', $cycle->previous_cycle_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function fileName(Student $student, string $span = 'semestre'): string
    {
        $name = Str::slug($student->last_name.' '.$student->first_name);
        $doc = $student->document ? '-'.$student->document : '';

        return "boletin-{$name}{$doc}{$this->suffix($span)}.pdf";
    }

    public function zipName(Cycle $cycle, string $span = 'semestre'): string
    {
        return 'boletines-'.Str::slug($cycle->name ?? $cycle->code).$this->suffix(BoletinService::span($span, $cycle)).'.zip';
    }

    private function suffix(string $span): string
    {
        return match ($span) {
            'm1' => '-modulo-1',
            'm2' => '-modulo-2',
            'anio' => '-anual',
            default => '',
        };
    }
}
