<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\Student;
use App\Support\ResizesInstitutionLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class BoletinPdfExporter
{
    use ResizesInstitutionLogo;

    public function __construct(private BoletinService $service) {}

    public function pdfForStudent(Cycle $cycle, Student $student, bool $bothSemesters = false): DomPdf
    {
        return Pdf::loadView('pdf.boletin', [
            'boletin' => $this->service->generate($student, $cycle, $bothSemesters),
            'passingScore' => BoletinService::PASSING_SCORE,
            'logo' => $this->institutionLogo(),
        ])->setPaper('letter');
    }

    /**
     * Build a ZIP with one boletín PDF per enrolled student. When
     * $bothSemesters is set, only students enrolled in the previous cycle too
     * are included. Returns the temp file path, or null when there are none.
     */
    public function zipForCycle(Cycle $cycle, bool $bothSemesters = false): ?string
    {
        $students = $this->students($cycle, $bothSemesters);

        if ($students->isEmpty()) {
            return null;
        }

        $logo = $this->institutionLogo();

        $zipPath = tempnam(sys_get_temp_dir(), 'bol').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($students as $student) {
            $pdf = Pdf::loadView('pdf.boletin', [
                'boletin' => $this->service->generate($student, $cycle, $bothSemesters),
                'passingScore' => BoletinService::PASSING_SCORE,
                'logo' => $logo,
            ])->setPaper('letter');

            $zip->addFromString($this->fileName($student), $pdf->output());

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

    public function fileName(Student $student): string
    {
        $name = Str::slug($student->last_name.' '.$student->first_name);
        $doc = $student->document ? '-'.$student->document : '';

        return "boletin-{$name}{$doc}.pdf";
    }

    public function zipName(Cycle $cycle): string
    {
        return 'boletines-'.Str::slug($cycle->name ?? $cycle->code).'.zip';
    }
}
