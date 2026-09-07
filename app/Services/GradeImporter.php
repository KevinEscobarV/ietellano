<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Support\XlsxReader;

class GradeImporter
{
    public function __construct(private readonly XlsxReader $reader) {}

    /**
     * Import a single grade xlsx. The file name (without extension) must match a course code.
     * Pass $persist = false to analyse the file without writing grades (report mode).
     *
     * @return array{course: ?string, matched: int, unmatched: int, blank: int, unmatched_rows: list<array{name: string, email: ?string, document: ?string}>}
     */
    public function import(string $path, ?string $originalName = null, bool $persist = true): array
    {
        $code = pathinfo($originalName ?? $path, PATHINFO_FILENAME);
        $course = Course::where('code', $code)->first();

        $result = ['course' => $code, 'matched' => 0, 'unmatched' => 0, 'blank' => 0, 'unmatched_rows' => []];

        if ($course === null) {
            $result['course'] = null;

            return $result;
        }

        foreach ($this->reader->rows($path) as $row) {
            $email = $this->column($row, 'correo');
            $document = $this->column($row, 'Número de ID');
            $student = $this->matchStudent($email, $document);

            if ($student === null) {
                $result['unmatched']++;
                $result['unmatched_rows'][] = [
                    'name' => trim(($this->column($row, 'Nombre') ?? '').' '.($this->column($row, 'Apellido') ?? '')),
                    'email' => $email,
                    'document' => $document,
                ];

                continue;
            }

            $score = $this->parseScore($this->column($row, 'Total del curso'));

            if ($score === null) {
                $result['blank']++;
            }

            if ($persist) {
                Grade::updateOrCreate(
                    ['course_id' => $course->id, 'student_id' => $student->id],
                    ['score' => $score],
                );
            }

            $result['matched']++;
        }

        return $result;
    }

    private function matchStudent(?string $email, ?string $document): ?Student
    {
        if ($email !== null && $email !== '') {
            $student = Student::where('email', $email)->first();

            if ($student !== null) {
                return $student;
            }
        }

        if ($document !== null && $document !== '') {
            return Student::where('document', $document)->first();
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function column(array $row, string $needle): ?string
    {
        foreach ($row as $name => $value) {
            if (stripos($name, $needle) !== false) {
                return trim($value);
            }
        }

        return null;
    }

    private function parseScore(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $raw = str_replace(',', '.', trim($raw));

        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }
}
