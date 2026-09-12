@php
    // Misma paleta del Decreto 1290 que usa la planilla del docente, para que
    // un color signifique lo mismo en todo el sistema.
    $barFor = [
        'Superior' => 'bg-emerald-500',
        'Alto' => 'bg-emerald-400',
        'Básico' => 'bg-amber-400',
        'Bajo' => 'bg-rose-500',
    ];

    $card = 'rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
    $label = 'text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
@endphp

<section class="w-full">
    {{-- Encabezado --}}
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Hola, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:subheading>{{ __('Así va el semestre.') }}</flux:subheading>
        </div>

        @if ($terms->count() > 1)
            <div class="flex flex-wrap gap-1.5">
                @foreach ($terms as $option)
                    <button
                        type="button"
                        wire:key="term-{{ $option }}"
                        wire:click="$set('term', '{{ $option }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-sm font-medium transition',
                            'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $term === $option,
                            'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $term !== $option,
                        ])
                    >{{ $option }}</button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Cifras del semestre --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="{{ $card }} p-4">
            <div class="{{ $label }}">{{ __('Estudiantes') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $enrollment['total'] }}</div>
        </div>

        <div class="{{ $card }} p-4">
            <div class="{{ $label }}">{{ __('Notas puestas') }}</div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $progress['percent'] }}%</span>
                <span class="text-sm tabular-nums text-zinc-500">{{ $progress['done'] }} / {{ $progress['expected'] }}</span>
            </div>
        </div>

        <div class="{{ $card }} p-4">
            <div class="{{ $label }}">{{ __('Promedio del semestre') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                {{ $performance['average'] !== null ? number_format($performance['average'], 2) : '—' }}
            </div>
        </div>

        <div @class([
            'p-4 rounded-xl border',
            'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10' => $atRisk['total'] > 0,
            'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => $atRisk['total'] === 0,
        ])>
            <div class="{{ $label }}">{{ __('En riesgo') }}</div>
            <div @class([
                'mt-1 text-2xl font-semibold tabular-nums',
                'text-rose-700 dark:text-rose-300' => $atRisk['total'] > 0,
                'text-zinc-900 dark:text-zinc-100' => $atRisk['total'] === 0,
            ])>{{ $atRisk['total'] }}</div>
        </div>
    </div>

    {{-- Avance de calificación y pendientes --}}
    <div class="mb-4 grid items-start gap-4 lg:grid-cols-3">
        <div class="{{ $card }} p-5 lg:col-span-2">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <flux:heading size="lg">{{ __('Avance de calificación') }}</flux:heading>
                <span class="text-sm tabular-nums text-zinc-500">{{ __(':done de :total notas', ['done' => number_format($progress['done']), 'total' => number_format($progress['expected'])]) }}</span>
            </div>

            @forelse ($progress['cycles'] as $line)
                <div class="mb-3 last:mb-0">
                    <div class="mb-1 flex items-baseline justify-between gap-4 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $line['label'] }}</span>
                        <span class="tabular-nums text-zinc-500">{{ $line['done'] }} / {{ $line['expected'] }} · {{ $line['percent'] }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $line['percent'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-zinc-400">{{ __('No hay ciclos en este semestre.') }}</p>
            @endforelse

            @if (count($behind) > 0)
                <div class="mt-6 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <div class="{{ $label }} mb-2">{{ __('Cursos con más notas pendientes') }}</div>
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($behind as $course)
                            <li class="flex items-center justify-between gap-4 py-2 text-sm">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $course['subject'] }}@if ($course['group']) <span class="font-normal text-zinc-500">· {{ $course['group'] }}</span>@endif
                                    </div>
                                    <div class="truncate text-xs text-zinc-500">{{ $course['teacher'] ?? __('Sin docente') }}</div>
                                </div>
                                <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium tabular-nums text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                    {{ __('faltan :count', ['count' => $course['missing']]) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="{{ $card }} p-5">
            <flux:heading size="lg" class="mb-4">{{ __('Pendientes') }}</flux:heading>

            <ul class="space-y-2">
                @foreach ($alerts as $alert)
                    @php
                        $warning = $alert['tone'] === 'warning';
                        $tone = $warning
                            ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200';
                    @endphp

                    <li @class(['rounded-lg border', $tone])>
                        @if ($alert['route'])
                            <a href="{{ route($alert['route']) }}" wire:navigate class="flex items-center gap-2.5 p-3 text-sm transition hover:opacity-75">
                                <flux:icon.exclamation-triangle variant="micro" class="shrink-0" />
                                <span class="flex-1">{{ $alert['text'] }}</span>
                                <flux:icon.chevron-right variant="micro" class="shrink-0 opacity-60" />
                            </a>
                        @else
                            <div class="flex items-center gap-2.5 p-3 text-sm">
                                <flux:icon.check-circle variant="micro" class="shrink-0" />
                                <span>{{ $alert['text'] }}</span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Matrícula, desempeño y riesgo --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="{{ $card }} p-5">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <flux:heading size="lg">{{ __('Matrícula') }}</flux:heading>
                <span class="text-sm tabular-nums text-zinc-500">{{ $enrollment['total'] }}</span>
            </div>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($enrollment['cycles'] as $line)
                    <li class="py-2.5 first:pt-0 last:pb-0">
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $line['label'] }}</span>
                            <span class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $line['total'] }}</span>
                        </div>
                        <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-zinc-500">
                            {{-- Con un solo grupo el desglose repetiría el total. --}}
                            @if (count($line['groups']) > 1)
                                @foreach ($line['groups'] as $group)
                                    <span class="tabular-nums">{{ $group['name'] }}: {{ $group['total'] }}</span>
                                @endforeach
                            @endif
                            @if ($line['new'] > 0)
                                <span class="text-emerald-600 dark:text-emerald-400">
                                    {{ trans_choice('{1}:count nuevo|[2,*]:count nuevos', $line['new'], ['count' => $line['new']]) }}
                                </span>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-zinc-400">{{ __('Sin matrículas.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="{{ $card }} p-5">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <flux:heading size="lg">{{ __('Desempeño') }}</flux:heading>
                <span class="text-sm tabular-nums text-zinc-500">{{ number_format($performance['total']) }}</span>
            </div>

            @if ($performance['total'] === 0)
                <p class="py-6 text-center text-sm text-zinc-400">{{ __('Todavía no hay notas cargadas en este semestre.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($performance['levels'] as $level)
                        <div>
                            <div class="mb-1 flex items-baseline justify-between gap-4 text-sm">
                                <span class="text-zinc-700 dark:text-zinc-300">{{ __($level['name']) }}</span>
                                <span class="tabular-nums text-zinc-500">{{ $level['total'] }} · {{ $level['percent'] }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full {{ $barFor[$level['name']] }}" style="width: {{ $level['percent'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $card }} p-5">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <flux:heading size="lg">{{ __('En riesgo') }}</flux:heading>
                <span class="text-sm tabular-nums text-zinc-500">{{ $atRisk['total'] }}</span>
            </div>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($atRisk['students'] as $student)
                    <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $student['name'] }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $student['cycle'] }}</div>
                        </div>
                        <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">
                            {{ number_format($student['average'], 2) }}
                        </span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-zinc-400">
                        {{ $performance['total'] === 0 ? __('Se calcula cuando haya notas.') : __('Nadie por debajo de 3.0.') }}
                    </li>
                @endforelse
            </ul>

            @if ($atRisk['total'] > count($atRisk['students']))
                <p class="mt-3 text-xs text-zinc-500">{{ __('y :count más', ['count' => $atRisk['total'] - count($atRisk['students'])]) }}</p>
            @endif
        </div>
    </div>
</section>
