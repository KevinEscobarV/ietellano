<section class="w-full">
    <style>
        @media print {
            @page { margin: 1.4cm; }
            body * { visibility: hidden !important; }
            #boletin, #boletin * { visibility: visible !important; }
            #boletin { position: absolute; inset: 0; width: 100%; border: 0 !important; padding: 0 !important; }
        }
    </style>

    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between print:hidden">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="document-text" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Boletines') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Generar boletín de notas por estudiante') }}</flux:subheading>
        </div>
        @php
            $downloadParams = $bothSemesters ? ['both' => 1] : [];
        @endphp
        <div class="flex items-center gap-3">
            @if ($cycleId)
                <flux:button variant="filled" icon="arrow-down-tray" :href="route('admin.boletines.download', ['cycle' => $cycleId] + $downloadParams)" target="_blank">
                    {{ __('Descargar ciclo (ZIP)') }}
                </flux:button>
            @endif
            @if ($boletin)
                <flux:button variant="filled" icon="document-arrow-down" :href="route('admin.boletines.download-student', ['cycle' => $cycleId, 'student' => $studentId] + $downloadParams)" target="_blank">
                    {{ __('Descargar PDF') }}
                </flux:button>
                <flux:button variant="primary" icon="printer" x-on:click="window.print()">
                    {{ __('Imprimir / PDF') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Selectors --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 print:hidden">
        <flux:select wire:model.live="cycleId" :label="__('Ciclo')" :placeholder="__('Selecciona un ciclo')">
            @foreach ($cycles as $cycle)
                <flux:select.option :value="$cycle->id">{{ $cycle->label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="studentId" :label="__('Estudiante')" :placeholder="__('Selecciona un estudiante')" :disabled="! $cycleId">
            @foreach ($students as $student)
                <flux:select.option :value="$student->id">{{ $student->last_name }} {{ $student->first_name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($hasPrevious)
        <div class="mb-6 print:hidden">
            <flux:checkbox wire:model.live="bothSemesters" :label="__('Incluir semestre anterior (dos semestres)')" />
        </div>
    @endif

    @if ($boletin)
        {{-- Report Card --}}
        <div id="boletin" class="mx-auto max-w-3xl rounded-xl border border-zinc-200 bg-white p-8 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 print:max-w-none">
            {{-- Institution header --}}
            <div class="mb-6 flex items-center justify-center gap-4 text-center">
                <img src="{{ asset('images/logo.png') }}" alt="{{ __('Logo') }}" class="size-20 shrink-0 object-contain">
                <div>
                    <flux:heading size="lg" class="uppercase tracking-wide">{{ config('app.institution') }}</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Educación de adultos · Validación de bachillerato') }}</p>
                    <p class="mt-3 text-base font-semibold">{{ __('CERTIFICADO DE CALIFICACIONES') }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $boletin['cycle_label'] }} · {{ __('Año') }} {{ $boletin['cycle']->year }} · {{ __('Semestre') }} {{ $boletin['cycle']->semester }}
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
                @else
                    <div><span class="text-zinc-500">{{ __('Periodos') }}:</span> <span class="font-medium">1 y 2</span></div>
                @endif
            </div>

            {{-- Grades table --}}
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-y border-zinc-300 text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-600">
                        <th class="py-2 text-left font-semibold">{{ __('Área / Materia') }}</th>
                        <th class="py-2 text-center font-semibold">{{ $boletin['both'] ? __('Sem 1') : __('P1') }}</th>
                        <th class="py-2 text-center font-semibold">{{ $boletin['both'] ? __('Sem 2') : __('P2') }}</th>
                        <th class="py-2 text-center font-semibold">{{ __('Final') }}</th>
                        <th class="py-2 text-center font-semibold">{{ __('Desempeño') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($boletin['lines'] as $line)
                        @php $failing = $line['final'] !== null && $line['final'] < $passingScore; @endphp
                        @php $col1 = $boletin['both'] ? ($line['sem1'] ?? null) : ($line['p1'] ?? null); @endphp
                        @php $col2 = $boletin['both'] ? ($line['sem2'] ?? null) : ($line['p2'] ?? null); @endphp
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="py-2">
                                <div class="font-medium">{{ $line['name'] }}</div>
                                @if ($line['type'] === 'subject' && ($line['teacher'] ?? null))
                                    <div class="text-xs text-zinc-400">{{ $line['teacher'] }}</div>
                                @endif
                                @foreach ($line['components'] as $component)
                                    <div class="pl-3 text-xs text-zinc-400">
                                        {{ $component['name'] }}@if ($component['teacher'] ?? null) · {{ $component['teacher'] }}@endif: {{ $component['final'] !== null ? number_format($component['final'], 2) : '—' }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="py-2 text-center tabular-nums">{{ $col1 !== null ? number_format($col1, 2) : '—' }}</td>
                            <td class="py-2 text-center tabular-nums">{{ $col2 !== null ? number_format($col2, 2) : '—' }}</td>
                            <td @class(['py-2 text-center font-semibold tabular-nums', 'text-red-600 dark:text-red-400' => $failing])>
                                {{ $line['final'] !== null ? number_format($line['final'], 2) : '—' }}
                            </td>
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
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-zinc-300 font-semibold dark:border-zinc-600">
                        <td class="py-3">{{ __('Promedio general') }}</td>
                        <td></td>
                        <td></td>
                        <td class="py-3 text-center tabular-nums">{{ $boletin['overall'] !== null ? number_format($boletin['overall'], 2) : '—' }}</td>
                        <td class="py-3 text-center text-xs">{{ $boletin['overall_performance'] ?? '—' }}</td>
                    </tr>
                </tfoot>
            </table>

            {{-- Scale legend --}}
            <p class="mt-4 text-xs text-zinc-400">
                {{ __('Escala: Superior (4.6–5.0) · Alto (4.0–4.5) · Básico (3.0–3.9) · Bajo (0.0–2.9). Nota mínima de aprobación: 3.0.') }}
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
    @elseif ($cycleId)
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700 print:hidden">
            <flux:icon name="user" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">{{ __('Selecciona un estudiante para ver su boletín.') }}</flux:text>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700 print:hidden">
            <flux:icon name="document-text" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">{{ __('Selecciona un ciclo y un estudiante para generar el boletín.') }}</flux:text>
        </div>
    @endif
</section>
