<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Teacher;
use App\Support\XlsxReader;
use Illuminate\Database\Seeder;

class TeachersSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const FILES = [
        'DOCENTES.xlsx',
        'DOCENTES MODULO2.xlsx',
    ];

    private int $assigned = 0;

    private int $missing = 0;

    public function run(XlsxReader $reader): void
    {
        foreach (self::FILES as $file) {
            $path = database_path('data'.DIRECTORY_SEPARATOR.$file);

            if (is_file($path)) {
                foreach ($reader->rows($path) as $row) {
                    $this->importRow($row);
                }
            }
        }

        $this->command?->info("Docentes: asignaciones={$this->assigned}, cursos no encontrados={$this->missing}");
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(array $row): void
    {
        $username = trim($row['username'] ?? '');

        if ($username === '') {
            return;
        }

        $teacher = Teacher::updateOrCreate(
            ['username' => $username],
            [
                'first_name' => trim($row['firstname'] ?? ''),
                'last_name' => trim($row['lastname'] ?? ''),
                'email' => trim($row['email'] ?? '') ?: null,
                'phone' => trim($row['phone1'] ?? '') ?: null,
            ],
        );

        for ($i = 1; $i <= 5; $i++) {
            $code = trim($row["course{$i}"] ?? '');

            if ($code === '') {
                continue;
            }

            Course::where('code', $code)->update(['teacher_id' => $teacher->id]) > 0
                ? $this->assigned++
                : $this->missing++;
        }
    }
}
