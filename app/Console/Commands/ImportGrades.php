<?php

namespace App\Console\Commands;

use App\Services\GradeImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

#[Signature('grades:import {path?}')]
#[Description('Import grade xlsx files (defaults to the bundled Calificaciones data).')]
class ImportGrades extends Command
{
    public function handle(GradeImporter $importer): int
    {
        $path = $this->argument('path')
            ?? database_path('data/Sistema de calificaciones Semipresencial/Calificaciones');

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $files = Finder::create()->files()->in($path)->name('*.xlsx');
        $totals = ['files' => 0, 'matched' => 0, 'unmatched' => 0, 'blank' => 0, 'skipped' => 0];

        foreach ($files as $file) {
            $result = $importer->import($file->getRealPath());

            if ($result['course'] === null) {
                $totals['skipped']++;
                $this->warn("Skipped (no course): {$file->getFilename()}");

                continue;
            }

            $totals['files']++;
            $totals['matched'] += $result['matched'];
            $totals['unmatched'] += $result['unmatched'];
            $totals['blank'] += $result['blank'];

            if ($result['unmatched'] > 0) {
                $this->line("{$result['course']}: {$result['matched']} ok, {$result['unmatched']} unmatched");
            }
        }

        $this->newLine();
        $this->info("Files: {$totals['files']}  Grades: {$totals['matched']}  Unmatched: {$totals['unmatched']}  Blank scores: {$totals['blank']}  Skipped files: {$totals['skipped']}");

        return self::SUCCESS;
    }
}
