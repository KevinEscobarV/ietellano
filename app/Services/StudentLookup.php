<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Str;

/**
 * La puerta del módulo público de consulta. El estudiante no tiene cuenta: se
 * identifica con su documento y lo confirma con su apellido, que es lo único
 * que impide que un número de cédula ajeno —un dato que circula con
 * facilidad— abra el boletín de otra persona.
 */
class StudentLookup
{
    /**
     * El estudiante cuyo documento y apellido coinciden, si lo hay. Devuelve
     * null en cualquier otro caso: quien consulta nunca sabe cuál de los dos
     * datos falló, ni si el documento está registrado.
     */
    public function find(string $document, string $lastName): ?Student
    {
        $document = self::normalizeDocument($document);
        $lastName = self::normalizeName($lastName);

        if ($document === '' || $lastName === '') {
            return null;
        }

        $student = Student::where('document', $document)->first();

        if ($student === null) {
            return null;
        }

        return $this->lastNameMatches($student, $lastName) ? $student : null;
    }

    /**
     * Sirve cualquiera de los dos apellidos, o los dos juntos. Se compara por
     * palabras completas para que "PEREZ" no entre por "PEREZA".
     */
    private function lastNameMatches(Student $student, string $lastName): bool
    {
        return str_contains(' '.self::normalizeName((string) $student->last_name).' ', ' '.$lastName.' ');
    }

    /**
     * El documento se teclea con puntos, espacios o guiones según quién lo
     * escriba; en la base está en limpio.
     */
    public static function normalizeDocument(string $document): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $document));
    }

    /**
     * Sin tildes, sin mayúsculas y sin espacios de más: "Peña" y "  PENA " son
     * el mismo apellido.
     */
    public static function normalizeName(string $name): string
    {
        $letters = (string) preg_replace('/[^A-Za-z ]/', ' ', Str::ascii($name));

        return trim((string) preg_replace('/\s+/', ' ', Str::upper($letters)));
    }
}
