@php
    // La escala del Decreto 1290 con los mismos tonos del consolidado, para
    // que un color signifique lo mismo en todo el sistema.
    $levelColors = [
        'Superior' => ['dot' => 'bg-emerald-600', 'stroke' => 'stroke-emerald-600'],
        'Alto' => ['dot' => 'bg-emerald-400', 'stroke' => 'stroke-emerald-400'],
        'Básico' => ['dot' => 'bg-amber-400', 'stroke' => 'stroke-amber-400'],
        'Bajo' => ['dot' => 'bg-rose-500', 'stroke' => 'stroke-rose-500'],
    ];

    $card = 'panel-card dash-rise';
    $label = 'text-sm text-zinc-500 dark:text-zinc-400';
    $figure = 'mt-0.5 text-2xl font-bold tracking-tight tabular-nums';
    $hint = 'mt-3 flex flex-wrap items-center gap-x-1.5 border-t border-zinc-100 pt-3 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400';
    $up = 'inline-flex items-center gap-0.5 font-semibold text-emerald-600 dark:text-emerald-400';
    $down = 'inline-flex items-center gap-0.5 font-semibold text-rose-600 dark:text-rose-400';

    $today = ucfirst(now()->translatedFormat('l j \d\e F'));
    $tallestCycle = max([1, ...array_column($enrollment['cycles'], 'total')]);
    $returning = $enrollment['total'] - array_sum(array_column($enrollment['cycles'], 'new'));
@endphp

<section class="w-full">
    {{-- La llave cambia con el semestre: al elegir otro, el tablero se vuelve
         a pintar entero y las gráficas se animan de nuevo. --}}
    <div wire:key="dashboard-{{ $term }}">
        {{-- Portada: saludo, semestre y el avance general de las notas --}}
        <div class="dash-hero dash-rise relative mb-4 overflow-hidden rounded-2xl p-6 text-white sm:p-8">
            <div class="dash-hero-glow" aria-hidden="true"><b></b><b></b><b></b></div>

            <div class="relative flex flex-wrap items-center justify-between gap-x-10 gap-y-6">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-white/70">{{ $today }}</div>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Hola, :name', ['name' => auth()->user()->name]) }}</h1>
                    <p class="mt-1.5 text-white/80">
                        {{ __('Así va el semestre.') }}
                        {{ trans_choice('{0}Aún no tiene ciclos.|{1}Un ciclo|[2,*]:count ciclos', count($enrollment['cycles']), ['count' => count($enrollment['cycles'])]) }}@if ($courseTotals['total'] > 0),
                            {{ trans_choice('{1}un curso|[2,*]:count cursos', $courseTotals['total'], ['count' => $courseTotals['total']]) }}.
                        @endif
                    </p>

                    @if ($terms->count() > 1)
                        <div class="mt-5 inline-flex flex-wrap gap-1 rounded-full bg-white/10 p-1 ring-1 ring-white/15 backdrop-blur-sm">
                            @foreach ($terms as $option)
                                <button
                                    type="button"
                                    wire:key="term-{{ $option }}"
                                    wire:click="$set('term', '{{ $option }}')"
                                    @class([
                                        'rounded-full px-3.5 py-1 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white',
                                        'bg-white text-zinc-900 shadow-sm' => $term === $option,
                                        'text-white/80 hover:bg-white/15 hover:text-white' => $term !== $option,
                                    ])
                                >{{ $option }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-5">
                    <div class="relative size-28 shrink-0 sm:size-32">
                        <svg viewBox="0 0 36 36" class="size-full -rotate-90" role="img" aria-label="{{ __(':percent% de las notas puestas', ['percent' => $progress['percent']]) }}">
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="3" class="stroke-white/20" />
                            @if ($progress['percent'] > 0)
                                <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="3" stroke-linecap="round" class="dash-ring stroke-white" stroke-dasharray="{{ $progress['percent'] }} 100" />
                            @endif
                        </svg>
                        <div class="absolute inset-0 grid place-items-center">
                            <div class="text-center">
                                <div class="text-2xl font-bold tabular-nums sm:text-3xl"><span x-data="countUp({{ $progress['percent'] }})" x-text="shown">{{ $progress['percent'] }}</span><span class="text-lg">%</span></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="font-semibold">{{ __('Notas puestas') }}</div>
                        <div class="mt-0.5 text-sm tabular-nums text-white/75">{{ __(':done de :total', ['done' => number_format($progress['done']), 'total' => number_format($progress['expected'])]) }}</div>
                        <div class="mt-0.5 text-sm tabular-nums text-white/75">
                            {{ trans_choice('{0}Ningún curso completo|{1}Un curso completo|[2,*]:count cursos completos', $courseTotals['complete'], ['count' => $courseTotals['complete']]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cifras del semestre --}}
        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="{{ $card }} p-4" style="--i: 1">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="{{ $label }}">{{ __('Estudiantes') }}</div>
                        <div class="{{ $figure }} text-zinc-900 dark:text-zinc-100"><span x-data="countUp({{ $enrollment['total'] }})" x-text="shown">{{ number_format($enrollment['total']) }}</span></div>
                    </div>
                    <span class="panel-tile-icon"><flux:icon.academic-cap variant="mini" /></span>
                </div>
                <div class="{{ $hint }}">
                    @if ($comparison['enrollment'] !== null && $comparison['enrollment'] !== 0)
                        <span class="{{ $comparison['enrollment'] > 0 ? $up : $down }}">
                            @if ($comparison['enrollment'] > 0)
                                <flux:icon.arrow-trending-up variant="micro" />
                            @else
                                <flux:icon.arrow-trending-down variant="micro" />
                            @endif
                            {{ ($comparison['enrollment'] > 0 ? '+' : '−').abs($comparison['enrollment']) }}%
                        </span>
                        {{ __('frente a :term', ['term' => $comparison['term']]) }}
                    @elseif ($comparison['enrollment'] === 0)
                        {{ __('igual que en :term', ['term' => $comparison['term']]) }}
                    @else
                        {{ __('matriculados en :term', ['term' => $term]) }}
                    @endif
                </div>
            </div>

            <div class="{{ $card }} p-4" style="--i: 2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="{{ $label }}">{{ __('Cursos') }}</div>
                        <div class="{{ $figure }} text-zinc-900 dark:text-zinc-100"><span x-data="countUp({{ $courseTotals['total'] }})" x-text="shown">{{ number_format($courseTotals['total']) }}</span></div>
                    </div>
                    <span class="panel-tile-icon"><flux:icon.book-open variant="mini" /></span>
                </div>
                <div class="{{ $hint }} tabular-nums">
                    <span class="font-semibold text-accent-content">{{ $courseTotals['complete'] }}</span>
                    {{ __('con todas sus notas') }}
                </div>
            </div>

            <div class="{{ $card }} p-4" style="--i: 3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="{{ $label }}">{{ __('Promedio del semestre') }}</div>
                        <div class="{{ $figure }} text-zinc-900 dark:text-zinc-100">
                            @if ($performance['average'] !== null)
                                <span x-data="countUp({{ $performance['average'] }}, 2)" x-text="shown">{{ number_format($performance['average'], 2) }}</span>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <span class="panel-tile-icon"><flux:icon.chart-bar variant="mini" /></span>
                </div>
                <div class="{{ $hint }}">
                    @if ($comparison['average'] !== null && $comparison['average'] != 0)
                        <span class="{{ $comparison['average'] > 0 ? $up : $down }}">
                            @if ($comparison['average'] > 0)
                                <flux:icon.arrow-trending-up variant="micro" />
                            @else
                                <flux:icon.arrow-trending-down variant="micro" />
                            @endif
                            {{ ($comparison['average'] > 0 ? '+' : '−').number_format(abs($comparison['average']), 2) }}
                        </span>
                        {{ __('frente a :term', ['term' => $comparison['term']]) }}
                    @else
                        {{ $performance['average'] !== null ? __('sobre 5.0') : __('aún sin notas') }}
                    @endif
                </div>
            </div>

            <div class="{{ $card }} p-4" style="--i: 4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="{{ $label }}">{{ __('En riesgo') }}</div>
                        <div @class([
                            $figure,
                            'text-rose-600 dark:text-rose-400' => $atRisk['total'] > 0,
                            'text-zinc-900 dark:text-zinc-100' => $atRisk['total'] === 0,
                        ])><span x-data="countUp({{ $atRisk['total'] }})" x-text="shown">{{ $atRisk['total'] }}</span></div>
                    </div>
                    <span @class([
                        'panel-tile-icon',
                        '[--tile:var(--color-rose-600)]' => $atRisk['total'] > 0,
                    ])><flux:icon.exclamation-triangle variant="mini" /></span>
                </div>
                <div class="{{ $hint }}">{{ __('con promedio menor a 3.0') }}</div>
            </div>
        </div>

        {{-- Avance de calificación y pendientes --}}
        <div class="mb-4 grid items-start gap-4 lg:grid-cols-3">
            <div class="{{ $card }} min-w-0 p-5 lg:col-span-2" style="--i: 5">
                <div class="mb-4 flex items-baseline justify-between gap-4">
                    <flux:heading size="lg">{{ __('Avance de calificación') }}</flux:heading>
                    <span class="text-sm tabular-nums text-zinc-500">{{ __(':done de :total notas', ['done' => number_format($progress['done']), 'total' => number_format($progress['expected'])]) }}</span>
                </div>

                @forelse ($progress['cycles'] as $line)
                    <div class="mb-3.5 last:mb-0">
                        <div class="mb-1.5 flex items-baseline justify-between gap-4 text-sm">
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $line['label'] }}</span>
                            <span class="tabular-nums text-zinc-500">
                                {{ $line['done'] }} / {{ $line['expected'] }} ·
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $line['percent'] }}%</span>
                            </span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="dash-grow-x h-full rounded-full bg-accent" style="width: {{ $line['percent'] }}%; --i: {{ $loop->index }}"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-zinc-400">{{ __('No hay ciclos en este semestre.') }}</p>
                @endforelse

                @if (count($behind) > 0)
                    <div class="mt-6 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <div class="{{ $label }} mb-2">{{ __('Cursos con más notas pendientes') }}</div>
                        {{-- Los roles de consulta ven el tablero pero no entran al panel de notas. --}}
                        @php $canOpenGrades = auth()->user()?->hasAnyRole(['super-admin', 'admin']); @endphp

                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($behind as $course)
                                <li>
                                    <a
                                        @if ($canOpenGrades) href="{{ route('admin.grade-editor.course', $course['id']) }}" wire:navigate @endif
                                        @class([
                                            '-mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-2 text-sm',
                                            'transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => $canOpenGrades,
                                        ])
                                    >
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">
                                                {{ $course['subject'] }}@if ($course['group']) <span class="font-normal text-zinc-500">· {{ $course['group'] }}</span>@endif
                                                <span class="font-normal text-zinc-500">· {{ __('M:n', ['n' => $course['period']]) }}</span>
                                            </div>
                                            <div class="truncate text-xs text-zinc-500">{{ $course['teacher'] ?? __('Sin docente') }}</div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium tabular-nums text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                            {{ __('faltan :count', ['count' => $course['missing']]) }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="{{ $card }} min-w-0 p-5" style="--i: 6">
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
                                <a href="{{ route($alert['route']) }}" wire:navigate class="group flex items-center gap-2.5 p-3 text-sm transition hover:opacity-80">
                                    <flux:icon.exclamation-triangle variant="micro" class="shrink-0" />
                                    <span class="flex-1">{{ $alert['text'] }}</span>
                                    <flux:icon.chevron-right variant="micro" class="shrink-0 opacity-60 transition-transform group-hover:translate-x-0.5" />
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
            <div class="{{ $card }} min-w-0 p-5" style="--i: 7">
                <div class="mb-1 flex items-baseline justify-between gap-4">
                    <flux:heading size="lg">{{ __('Matrícula por ciclo') }}</flux:heading>
                    <span class="text-sm tabular-nums text-zinc-500">{{ $enrollment['total'] }}</span>
                </div>

                @if ($enrollment['total'] === 0)
                    <p class="py-6 text-center text-sm text-zinc-400">{{ __('Sin matrículas.') }}</p>
                @else
                    <div class="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-accent"></span>{{ __('Antiguos') }} <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $returning }}</span></span>
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-accent/40"></span>{{ __('Nuevos') }} <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $enrollment['total'] - $returning }}</span></span>
                    </div>

                    {{-- Una columna por ciclo; el tramo claro de arriba son los que entran por primera vez. --}}
                    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
                        @foreach ($enrollment['cycles'] as $line)
                            <div class="group relative flex h-56 min-w-0 flex-1 flex-col items-center justify-end outline-none" tabindex="0">
                                <span class="mb-1 text-xs font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $line['total'] }}</span>

                                @if ($line['total'] > 0)
                                    <div
                                        class="dash-grow-y flex w-full max-w-11 flex-col gap-0.5 transition-opacity group-hover:opacity-85 group-focus-visible:opacity-85"
                                        style="height: calc((100% - 1.5rem) * {{ round($line['total'] / $tallestCycle, 4) }}); --i: {{ $loop->index }}"
                                    >
                                        @if ($line['new'] > 0)
                                            <div class="rounded-t bg-accent/40" style="flex: {{ $line['new'] }} 1 0%"></div>
                                        @endif
                                        @if ($line['total'] > $line['new'])
                                            <div @class(['bg-accent', 'rounded-t' => $line['new'] === 0]) style="flex: {{ $line['total'] - $line['new'] }} 1 0%"></div>
                                        @endif
                                    </div>
                                @endif

                                <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-zinc-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block group-focus-visible:block dark:bg-zinc-700">
                                    <div class="font-semibold">{{ $line['label'] }}</div>
                                    <div class="mt-0.5 tabular-nums text-white/80">
                                        {{ trans_choice('{1}:count estudiante|[0,*]:count estudiantes', $line['total'], ['count' => $line['total']]) }}@if ($line['new'] > 0),
                                            {{ trans_choice('{1}:count nuevo|[2,*]:count nuevos', $line['new'], ['count' => $line['new']]) }}
                                        @endif
                                    </div>
                                    {{-- Con un solo grupo el desglose repetiría el total. --}}
                                    @if (count($line['groups']) > 1)
                                        @foreach ($line['groups'] as $group)
                                            <div class="tabular-nums text-white/80">{{ $group['name'] }}: {{ $group['total'] }}</div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-2">
                        @foreach ($enrollment['cycles'] as $line)
                            <div class="min-w-0 flex-1 truncate text-center text-xs text-zinc-500 dark:text-zinc-400" title="{{ $line['label'] }}">{{ str($line['name'])->after('Ciclo ') }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="{{ $card }} min-w-0 p-5" style="--i: 8">
                <div class="mb-4 flex items-baseline justify-between gap-4">
                    <flux:heading size="lg">{{ __('Desempeño') }}</flux:heading>
                    <span class="text-sm tabular-nums text-zinc-500">{{ trans_choice('{1}:count nota|[0,*]:count notas', $performance['total'], ['count' => number_format($performance['total'])]) }}</span>
                </div>

                @if ($performance['total'] === 0)
                    <p class="py-6 text-center text-sm text-zinc-400">{{ __('Todavía no hay notas cargadas en este semestre.') }}</p>
                @else
                    <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-4">
                        <div class="relative size-40 shrink-0">
                            <svg viewBox="0 0 36 36" class="size-full -rotate-90" role="img" aria-label="{{ __('Reparto de las notas por nivel de desempeño') }}">
                                <circle cx="18" cy="18" r="15.915" fill="none" stroke-width="4" class="stroke-zinc-100 dark:stroke-zinc-800" />
                                @php $offset = 0; @endphp
                                @foreach ($performance['levels'] as $level)
                                    @php $share = $level['total'] / $performance['total'] * 100; @endphp
                                    @if ($share > 0)
                                        {{-- El medio punto que se le quita a cada tramo es la separación entre colores. --}}
                                        <circle
                                            cx="18" cy="18" r="15.915" fill="none" stroke-width="4"
                                            class="dash-ring {{ $levelColors[$level['name']]['stroke'] }}"
                                            stroke-dasharray="{{ round(max($share - ($share < 100 ? 0.6 : 0), 0.2), 3) }} 100"
                                            stroke-dashoffset="{{ round(-$offset, 3) }}"
                                        ><title>{{ __($level['name']) }}: {{ $level['total'] }} ({{ $level['percent'] }}%)</title></circle>
                                        @php $offset += $share; @endphp
                                    @endif
                                @endforeach
                            </svg>
                            <div class="absolute inset-0 grid place-items-center text-center">
                                <div>
                                    <div class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($performance['average'], 2) }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('promedio') }}</div>
                                </div>
                            </div>
                        </div>

                        <ul class="min-w-36 flex-1 space-y-2 text-sm">
                            @foreach ($performance['levels'] as $level)
                                <li class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full {{ $levelColors[$level['name']]['dot'] }}"></span>
                                    <span class="flex-1 text-zinc-700 dark:text-zinc-300">{{ __($level['name']) }}</span>
                                    <span class="tabular-nums text-zinc-500">{{ $level['total'] }}</span>
                                    <span class="w-10 text-end font-semibold tabular-nums text-zinc-800 dark:text-zinc-200">{{ $level['percent'] }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="{{ $card }} min-w-0 p-5" style="--i: 9">
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
    </div>
</section>
