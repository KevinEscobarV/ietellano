<?php

namespace App\Console\Commands;

use App\Services\GradeImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

#[Signature('grades:unmatched {path?}')]
#[Description('Report grade rows that could not be matched to an enrolled student.')]
class UnmatchedGrades extends Command
{
    public function handle(GradeImporter $importer): int
    {
        $path = $this->argument('path')
            ?? database_path('data/Sistema de calificaciones Semipresencial/Calificaciones');

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $rows = [];

        foreach (Finder::create()->files()->in($path)->name('*.xlsx') as $file) {
            $result = $importer->import($file->getRealPath(), $file->getFilename(), persist: false);

            foreach ($result['unmatched_rows'] as $unmatched) {
                $rows[] = [$result['course'] ?? $file->getFilename(), $unmatched['name'], $unmatched['email'], $unmatched['document']];
            }
        }

        if ($rows === []) {
            $this->info('No unmatched grade rows found.');

            return self::SUCCESS;
        }

        $this->table(['Curso', 'Nombre', 'Email', 'Documento'], $rows);

        $csvPath = storage_path('app/reportes/notas-sin-cruzar.csv');
        File::ensureDirectoryExists(dirname($csvPath));
        $handle = fopen($csvPath, 'w');
        fputcsv($handle, ['curso', 'nombre', 'email', 'documento'], ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);

        $this->newLine();
        $this->info(count($rows).' filas sin cruzar. CSV: '.$csvPath);

        return self::SUCCESS;
    }
}
