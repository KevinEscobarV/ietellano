@props(['boletin', 'passingScore' => \App\Services\BoletinService::PASSING_SCORE])

{{-- El boletín en pantalla, tal como sale impreso. Lo comparten el módulo de
     administración y la consulta pública del estudiante: una sola maqueta, para
     que el papel que reparte la institución y el que descarga el estudiante
     digan exactamente lo mismo. --}}

<style>
    @media print {
        @page { margin: 1.4cm; }
        body * { visibility: hidden !important; }
        #boletin, #boletin * { visibility: visible !important; }
        #boletin { position: absolute; inset: 0; width: 100%; border: 0 !important; padding: 0 !important; }
    }
</style>

<div id="boletin" class="mx-auto w-full max-w-3xl rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none print:shadow-none p-4 sm:p-8 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 print:max-w-none">
    {{-- Institution header --}}
    <div class="mb-6 flex items-center justify-center gap-4 text-center">
        <img src="{{ asset('images/logo.png') }}" alt="{{ __('Logo') }}" class="size-20 shrink-0 object-contain">
        <div>
            <flux:heading size="lg" class="uppercase tracking-wide">{{ config('app.institution') }}</flux:heading>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Educación de adultos · Validación de bachillerato') }}</p>
            <p class="mt-3 text-base font-semibold">{{ __('BOLETÍN DE CALIFICACIONES') }}</p>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $boletin['cycle_label'] }} · {{ __('Año') }} {{ $boletin['cycle']->year }} · {{ __('Semestre') }} {{ $boletin['cycle']->semester }}@if ($boletin['period']) · {{ __('Módulo :n', ['n' => $boletin['period']]) }}@endif
            </p>
        </div>
    </div>

    {{-- Student meta --}}
    <div class="mb-5 grid grid-cols-2 gap-x-6 gap-y-1 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800/50">
        <div><span class="text-zinc-500">{{ __('Estudiante') }}:</span> <span class="font-medium">{{ $boletin['student']->fullName() }}</span></div>
        <div><span class="text-zinc-500">{{ __('Documento') }}:</span> <span class="font-medium">{{ $boletin['student']->document ?? '—' }}</span></div>
        <div><span class="text-zinc-500">{{ __('Grupo') }}:</span> <span class="font-medium">{{ $boletin['group'] ?? '—' }}</span></div>
        @if ($boletin['both'])
            <div><span class="text-zinc-500">{{ __('Semestres') }}:</span> <span class="font-medium">{{ $boletin['previous']->year }} – {{ $boletin['cycle']->year }}</span></div>
        @elseif ($boletin['period'])
            <div><span class="text-zinc-500">{{ __('Módulo') }}:</span> <span class="font-medium">{{ $boletin['period'] }}</span></div>
        @else
            <div><span class="text-zinc-500">{{ __('Módulos') }}:</span> <span class="font-medium">1 y 2</span></div>
        @endif
    </div>

    @php
        $num = fn ($value) => $value !== null ? number_format($value, 2) : '—';
        $fails = fn ($value) => $value !== null && $value < $passingScore;
        $absences = fn (array $line) => ($line['held'] ?? 0) > 0 ? ($line['absences'] ?? 0) : '—';
        $details = $boletin['detail_columns'];
    @endphp

    {{-- Grades table. Un área va en negrita con su promedio y, debajo, una
         fila por cada materia con sus propias notas en las mismas columnas:
         así se ve de dónde sale cada número del área. --}}
    <div class="overflow-x-auto print:overflow-visible">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-y border-zinc-300 text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-600">
                <th class="py-2 text-left font-semibold">{{ __('Área / Materia') }}</th>
                @foreach ($details as $detail)
                    <th class="py-2 text-center font-semibold sm:w-16">{{ $detail['label'] }}</th>
                @endforeach
                <th class="py-2 text-center font-semibold sm:w-16">{{ $boletin['final_label'] }}</th>
                <th class="py-2 text-center font-semibold sm:w-16">{{ __('Fallas') }}</th>
                <th class="py-2 text-center font-semibold sm:w-28">{{ __('Desempeño') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($boletin['lines'] as $line)
                @php $isArea = $line['type'] === 'area'; @endphp
                <tr @class(['border-b border-zinc-100 dark:border-zinc-800' => ! $isArea])>
                    <td class="py-2 pe-3">
                        <div class="font-medium">{{ $line['name'] }}</div>
                        @if ($isArea)
                            <div class="text-xs text-zinc-400">{{ __('Promedio de :subjects', ['subjects' => implode(' y ', array_column($line['components'], 'name'))]) }}</div>
                        @elseif ($line['teacher'] ?? null)
                            <div class="text-xs text-zinc-400">{{ $line['teacher'] }}</div>
                        @endif
                    </td>
                    @foreach ($details as $detail)
                        <td class="py-2 text-center tabular-nums">{{ $num($line[$detail['key']] ?? null) }}</td>
                    @endforeach
                    <td @class(['py-2 text-center font-semibold tabular-nums', 'text-red-600 dark:text-red-400' => $fails($line['final'])])>{{ $num($line['final']) }}</td>
                    <td @class(['py-2 text-center tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => ! $isArea && ($line['lost_by_absence'] ?? false)])>{{ $isArea ? '' : $absences($line) }}</td>
                    <td class="py-2 text-center">
                        @if ($line['performance'])
                            <span @class([
                                'inline-block rounded px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => in_array($line['performance'], ['Superior', 'Alto']),
                                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $line['performance'] === 'Básico',
                                'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $line['performance'] === 'Bajo',
                            ])>{{ $line['performance'] }}</span>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </td>
                </tr>

                @foreach ($line['components'] as $component)
                    <tr @class(['text-zinc-600 dark:text-zinc-300', 'border-b border-zinc-100 dark:border-zinc-800' => $loop->last])>
                        <td class="py-1.5 ps-4 pe-3">
                            <div class="border-s-2 border-zinc-200 ps-2.5 dark:border-zinc-700">
                                <div>{{ $component['name'] }}</div>
                                @if ($component['teacher'] ?? null)
                                    <div class="text-xs text-zinc-400">{{ $component['teacher'] }}</div>
                                @endif
                            </div>
                        </td>
                        @foreach ($details as $detail)
                            <td class="py-1.5 text-center tabular-nums">{{ $num($component[$detail['key']] ?? null) }}</td>
                        @endforeach
                        <td @class(['py-1.5 text-center font-medium tabular-nums', 'text-red-600 dark:text-red-400' => $fails($component['final'])])>{{ $num($component['final']) }}</td>
                        <td @class(['py-1.5 text-center tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => $component['lost_by_absence'] ?? false])>{{ $absences($component) }}</td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-zinc-300 font-semibold dark:border-zinc-600">
                <td class="py-3">{{ __('Promedio general') }}</td>
                @foreach ($details as $detail)
                    <td></td>
                @endforeach
                <td class="py-3 text-center tabular-nums">{{ $num($boletin['overall']) }}</td>
                <td></td>
                <td class="py-3 text-center text-xs">{{ $boletin['overall_performance'] ?? '—' }}</td>
            </tr>
        </tfoot>
    </table>
    </div>

    {{-- Scale legend --}}
    <p class="mt-4 text-xs text-zinc-400">
        {{ __('Escala: Superior (4.6–5.0) · Alto (4.0–4.5) · Básico (3.0–3.9) · Bajo (0.0–2.9). Nota mínima de aprobación: 3.0.') }}
        @if ($boletin['has_areas'])
            {{ __('La nota de un área es el promedio de las notas de sus materias; una materia por debajo de 3.0 sale en rojo aunque el área apruebe.') }}
        @endif
    </p>

    {{-- Signatures --}}
    <div class="mt-12 grid grid-cols-2 gap-10 text-center text-sm">
        <div>
            <div class="border-t border-zinc-400 pt-1">{{ __('Director de grupo') }}</div>
        </div>
        <div>
            <div class="border-t border-zinc-400 pt-1">{{ __('Rector') }}</div>
        </div>
    </div>
</div>
