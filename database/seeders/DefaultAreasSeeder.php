<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Course;
use App\Models\Cycle;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Las áreas que juntan dos materias en una sola nota del boletín y del
 * consolidado. Son las mismas que el certificado promedia
 * (`CertificateService::OFFICIAL_AREAS`): sin ellas el boletín muestra las
 * materias sueltas y su promedio general no coincide con el del certificado.
 *
 * Se puede correr sola y las veces que haga falta:
 * `php artisan db:seed --class=DefaultAreasSeeder`.
 */
class DefaultAreasSeeder extends Seeder
{
    /**
     * @var list<array{name: string, code: string, subjects: list<string>}>
     */
    public const AREAS = [
        ['name' => 'Ciencias Naturales', 'code' => 'CN', 'subjects' => ['FIS', 'QUI']],
        ['name' => 'Humanidades', 'code' => 'HUM', 'subjects' => ['LEN', 'ING']],
    ];

    /**
     * Crea cada área en los ciclos que dictan todas sus materias. Un área que
     * ya existe se deja como está, con el nombre y el orden que tenga.
     */
    public function run(): void
    {
        $subjectIds = Subject::query()->pluck('id', 'code');

        $taught = Course::query()
            ->select('cycle_id', 'subject_id')
            ->distinct()
            ->get()
            ->groupBy('cycle_id')
            ->map(fn ($courses) => $courses->pluck('subject_id')->all());

        foreach (Cycle::query()->pluck('id') as $cycleId) {
            foreach (self::AREAS as $order => $definition) {
                $members = array_map(fn (string $code) => $subjectIds[$code] ?? null, $definition['subjects']);

                if (in_array(null, $members, true) || array_diff($members, $taught[$cycleId] ?? []) !== []) {
                    continue;
                }

                Area::firstOrCreate(
                    ['cycle_id' => $cycleId, 'name' => $definition['name']],
                    ['code' => $definition['code'], 'display_order' => $order],
                )->subjects()->syncWithoutDetaching($members);
            }
        }
    }
}
