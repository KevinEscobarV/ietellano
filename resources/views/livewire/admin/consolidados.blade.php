<section class="w-full">
    @php
        $num = fn ($v) => $v !== null ? number_format($v, 2) : '—';
        $isFail = fn ($v) => $v !== null && $v < $passingScore;
    @endphp

    {{-- Encabezado --}}
    <div class="mb-8 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="mb-1.5 flex items-center gap-3">
                <flux:icon name="table-cells" class="panel-icon" />
                <flux:heading size="xl" level="1">{{ __('Consolidados') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Las notas de todo un ciclo en una tabla, para ver de un vistazo cómo le fue a cada estudiante y a cada materia.') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($year !== '' && $semester !== '')
                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" icon="arrow-down-tray" icon-trailing="chevron-down" data-tour="term-download">{{ __('Todo el semestre') }}</flux:button>

                    <flux:menu>
                        <flux:menu.heading>{{ __(':year · Semestre :n', ['year' => $year, 'n' => $semester]) }}</flux:menu.heading>
                        @php $termRoute = ['year' => $year, 'semester' => $semester] + ($consolidado && $consolidado['view'] !== 'definitiva' ? ['vista' => $consolidado['view']] : []); @endphp
                        <flux:menu.item icon="table-cells" :href="route('admin.consolidados.download-term', $termRoute + ['formato' => 'xlsx'])" target="_blank">
                            {{ __('Excel, una hoja por ciclo') }}
                        </flux:menu.item>
                        <flux:menu.item icon="document-arrow-down" :href="route('admin.consolidados.download-term', $termRoute + ['formato' => 'pdf'])" target="_blank">
                            {{ __('PDF, una página por ciclo') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif

            <x-tour-button name="consolidados" />
        </div>
    </div>

    <x-term-cycle-steps :years="$years" :semesters="$semesters" :cycle-cards="$cycleCards" :cycle-id="$cycleId" />

    @if ($consolidado)
        @php
            $s = $consolidado['summary'];
            $status = $consolidado['status'];
            $views = ['definitiva' => __('Definitiva'), 'm1' => __('Módulo 1'), 'm2' => __('Módulo 2')];
        @endphp

        {{-- 3 · Consolidado --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="flex size-6 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground">3</span>
                <flux:heading size="lg">{{ __('Consolidado de :cycle', ['cycle' => $selected->name]) }}</flux:heading>
            </div>

            <div class="flex flex-wrap gap-2" data-tour="downloads">
                <flux:button size="sm" icon="table-cells" :href="route('admin.consolidados.download', ['cycle' => $selected->id, 'formato' => 'xlsx'] + $downloadParams)" target="_blank">
                    {{ __('Excel') }}
                </flux:button>
                <flux:button size="sm" icon="document-arrow-down" :href="route('admin.consolidados.download', ['cycle' => $selected->id, 'formato' => 'pdf'] + $downloadParams)" target="_blank">
                    {{ __('PDF') }}
                </flux:button>
            </div>
        </div>

        @php $parts = $consolidado['yearParts']; @endphp

        {{-- La primera mitad de un ciclo de un año: el año se lee en la segunda. --}}
        @if ($consolidado['continuesIn'])
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                <div class="flex items-start gap-2">
                    <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0" />
                    <span>
                        {{ __('Este ciclo dura un año y sigue en :next (:term). Aquí ves solo este semestre; el consolidado del año completo está en :next.', [
                            'next' => $consolidado['continuesIn']->name,
                            'term' => $consolidado['continuesIn']->year.'-'.$consolidado['continuesIn']->semester,
                        ]) }}
                    </span>
                </div>
                <flux:button size="sm" icon-trailing="arrow-right" wire:click="$set('cycleId', '{{ $consolidado['continuesIn']->id }}')">
                    {{ __('Ver el año en :next', ['next' => $consolidado['continuesIn']->name]) }}
                </flux:button>
            </div>
        @endif

        {{-- Qué se lee --}}
        <div class="mb-4 flex flex-wrap items-center gap-3" data-tour="views">
            @if ($parts)
                <div class="inline-flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800" role="group" aria-label="{{ __('Año o semestre') }}">
                    @foreach (['anio' => __('Año completo (:first + :second)', ['first' => $parts['previous'], 'second' => $parts['current']]), 'semestre' => __('Solo :level', ['level' => $parts['current']])] as $value => $label)
                        <button
                            type="button"
                            wire:key="span-{{ $value }}"
                            wire:click="$set('span', '{{ $value }}')"
                            aria-pressed="{{ $consolidado['year'] === ($value === 'anio') ? 'true' : 'false' }}"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' => $consolidado['year'] === ($value === 'anio'),
                                'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $consolidado['year'] !== ($value === 'anio'),
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            @endif

            @unless ($consolidado['year'])
                <div class="inline-flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800" role="group" aria-label="{{ __('Qué notas ver') }}">
                    @foreach ($views as $value => $label)
                        <button
                            type="button"
                            wire:key="view-{{ $value }}"
                            wire:click="$set('view', '{{ $value }}')"
                            aria-pressed="{{ $consolidado['view'] === $value ? 'true' : 'false' }}"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' => $consolidado['view'] === $value,
                                'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $consolidado['view'] !== $value,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            @endunless

            @if ($groups->count() > 1)
                <div class="w-44">
                    <flux:select wire:model.live="groupId" size="sm" :aria-label="__('Grupo')">
                        <flux:select.option value="">{{ __('Todos los grupos') }}</flux:select.option>
                        @foreach ($groups as $id => $name)
                            <flux:select.option :value="(string) $id">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <div class="w-48">
                <flux:select wire:model.live="sort" size="sm" :aria-label="__('Orden')">
                    <flux:select.option value="nombre">{{ __('Ordenar por nombre') }}</flux:select.option>
                    <flux:select.option value="puesto">{{ __('Ordenar por puesto') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($consolidado['year'])
            <p class="-mt-2 mb-4 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('La nota de cada materia es el promedio de la definitiva de :first y la de :second, la misma cuenta del certificado.', ['first' => $parts['previous'], 'second' => $parts['current']]) }}
            </p>
        @endif

        {{-- Sello --}}
        <div @class([
            'mb-6 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' => $status['definitive'],
            'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200' => ! $status['definitive'],
        ])>
            <flux:icon :name="$status['definitive'] ? 'check-badge' : 'clock'" class="mt-0.5 size-5 shrink-0" />
            <div>
                <span class="font-semibold">{{ $status['label'] }}.</span>
                {{ $status['detail'] }}.
                @unless ($status['definitive'])
                    <span class="block text-xs opacity-80 sm:inline">{{ __('Las notas todavía pueden cambiar.') }}</span>
                @endunless
            </div>
        </div>

        @if ($s['students'] === 0)
            <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
                <flux:icon name="users" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-400">{{ __('Este ciclo no tiene estudiantes matriculados.') }}</flux:text>
            </div>
        @else
            {{-- Lectura rápida --}}
            <div class="mb-6 space-y-3" data-tour="summary">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Estudiantes') }}</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $s['students'] }}</div>
                        @if ($s['without_previous'] > 0)
                            <div class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">{{ __(':n sin :level, van sin puesto', ['n' => $s['without_previous'], 'level' => $parts['previous']]) }}</div>
                        @endif
                        @if ($s['without_grades'] > 0)
                            <div class="mt-0.5 text-xs text-zinc-500">{{ trans_choice('{1}1 sin notas todavía|[2,*]:count sin notas todavía', $s['without_grades'], ['count' => $s['without_grades']]) }}</div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Promedio del ciclo') }}</div>
                        <div @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-red-600 dark:text-red-400' => $isFail($s['average']), 'text-zinc-900 dark:text-zinc-100' => ! $isFail($s['average'])])>{{ $num($s['average']) }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500">{{ $s['performance'] ?? __('Sin notas') }}</div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Aprueban todo') }}</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $s['all_passed'] }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500">
                            @if ($s['graded'] === 0)
                                {{ __('Sin notas') }}
                            @elseif ($consolidado['year'])
                                {{ __('de :n con los dos semestres · :pct %', ['n' => $s['graded'], 'pct' => round($s['all_passed'] * 100 / $s['graded'])]) }}
                            @else
                                {{ __('de :n · :pct %', ['n' => $s['graded'], 'pct' => round($s['all_passed'] * 100 / $s['graded'])]) }}
                            @endif
                        </div>
                    </div>

                    <div @class([
                        'rounded-xl border p-4',
                        'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' => $s['failing_total'] > 0,
                        'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => $s['failing_total'] === 0,
                    ])>
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Pierden alguna materia') }}</div>
                        <div @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-red-600 dark:text-red-400' => $s['failing_total'] > 0, 'text-zinc-900 dark:text-zinc-100' => $s['failing_total'] === 0])>{{ $s['failing_total'] }}</div>
                        <div class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">
                            {{ __('1: :a · 2: :b · 3 o más: :c', ['a' => $s['failing'][1], 'b' => $s['failing'][2], 'c' => $s['failing'][3]]) }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-3 text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Donde más se pierde') }}</div>

                        @forelse ($s['hardest'] as $hardest)
                            @php $rate = $hardest['graded'] > 0 ? round($hardest['failed'] * 100 / $hardest['graded']) : 0; @endphp
                            <div wire:key="hardest-{{ $loop->index }}" class="mb-2 last:mb-0">
                                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                    <span class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $hardest['name'] }}</span>
                                    <span class="shrink-0 tabular-nums text-zinc-500">{{ __(':failed de :graded pierden', ['failed' => $hardest['failed'], 'graded' => $hardest['graded']]) }}</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-red-500" style="width: {{ $rate }}%"></div>
                                </div>
                            </div>
                        @empty
                            <flux:text class="text-sm">{{ __('Nadie pierde ninguna materia.') }}</flux:text>
                        @endforelse
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-3 text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Desempeño de los promedios') }}</div>

                        @php
                            $levelColors = ['Superior' => 'bg-emerald-600', 'Alto' => 'bg-emerald-400', 'Básico' => 'bg-amber-400', 'Bajo' => 'bg-red-500'];
                        @endphp

                        @if ($s['graded'] > 0)
                            <div class="mb-3 flex h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" aria-hidden="true">
                                @foreach ($s['levels'] as $level => $count)
                                    @if ($count > 0)
                                        <div class="{{ $levelColors[$level] }} h-full" style="width: {{ $count * 100 / $s['graded'] }}%"></div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                            @foreach ($s['levels'] as $level => $count)
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2 shrink-0 rounded-full {{ $levelColors[$level] }}"></span>
                                    <span class="text-zinc-600 dark:text-zinc-400">{{ $level }}</span>
                                    <span class="ms-auto font-semibold tabular-nums text-zinc-900 sm:ms-0 dark:text-zinc-100">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($s['pending_cells'] > 0)
                    <div class="flex items-start gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">
                        <flux:icon name="information-circle" class="mt-0.5 size-4 shrink-0" />
                        <span>
                            {{ trans_choice('{1}1 nota está incompleta|[2,*]:count notas están incompletas', $s['pending_cells'], ['count' => $s['pending_cells']]) }}
                            {{ trans_choice('{1}(de 1 estudiante)|[2,*](de :count estudiantes)', $s['pending_students'], ['count' => $s['pending_students']]) }}:
                            {{ $consolidado['view'] === 'definitiva' ? __('les falta la nota de algún módulo. En la tabla salen en gris.') : __('les falta la nota de este módulo.') }}
                        </span>
                    </div>
                @endif

                @if ($consolidado['dropped'] !== [])
                    <details class="group rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none dark:border-zinc-700 dark:bg-zinc-900" data-tour="dropped">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="flex items-center gap-2">
                                <flux:icon name="user-minus" class="size-4 text-zinc-400" />
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ trans_choice('{1}1 estudiante cursó :first y no siguió en :second|[2,*]:count estudiantes cursaron :first y no siguieron en :second', count($consolidado['dropped']), [
                                        'count' => count($consolidado['dropped']),
                                        'first' => $parts['previous'],
                                        'second' => $parts['current'],
                                    ]) }}
                                </span>
                            </span>
                            <flux:icon name="chevron-down" class="size-4 shrink-0 text-zinc-400 transition group-open:rotate-180" />
                        </summary>

                        <div class="overflow-x-auto border-t border-zinc-100 dark:border-zinc-800">
                            <table class="min-w-full text-sm">
                                <thead class="text-xs text-zinc-500">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium">{{ __('Estudiante') }}</th>
                                        <th class="px-2 py-2 text-left font-medium whitespace-nowrap">{{ __('Grupo en :level', ['level' => $parts['previous']]) }}</th>
                                        <th class="px-2 py-2 text-center font-medium whitespace-nowrap">{{ __('Promedio en :level', ['level' => $parts['previous']]) }}</th>
                                        <th class="px-2 py-2 text-center font-medium whitespace-nowrap">{{ __('Perdió') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($consolidado['dropped'] as $student)
                                        <tr wire:key="dropped-{{ $student['id'] }}">
                                            <td class="px-4 py-1.5">
                                                <span class="text-zinc-800 dark:text-zinc-200">{{ $student['name'] }}</span>
                                                @if ($student['document'])
                                                    <span class="text-xs text-zinc-500">· {{ $student['document'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-2 py-1.5 text-xs whitespace-nowrap text-zinc-500">{{ $student['group'] ?? '—' }}</td>
                                            <td @class(['px-2 py-1.5 text-center font-semibold tabular-nums', 'text-red-600 dark:text-red-400' => $isFail($student['average']), 'text-zinc-800 dark:text-zinc-200' => ! $isFail($student['average'])])>{{ $num($student['average']) }}</td>
                                            <td @class(['px-2 py-1.5 text-center tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => $student['failed'] > 0, 'text-zinc-400' => $student['failed'] === 0])>{{ $student['failed'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>

            {{-- La tabla --}}
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700" data-tour="table">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="sticky left-0 z-10 min-w-40 bg-zinc-50 px-3 py-2 text-left font-medium dark:bg-zinc-800">{{ __('Estudiante') }}</th>
                            @if ($consolidado['showGroup'])
                                <th scope="col" class="px-2 py-2 text-left font-medium whitespace-nowrap">{{ __('Grupo') }}</th>
                            @endif
                            @foreach ($consolidado['columns'] as $column)
                                <th
                                    scope="col"
                                    wire:key="col-{{ $loop->index }}"
                                    class="min-w-20 px-2 py-2 text-center font-medium leading-tight"
                                    @if ($column['components'] !== []) title="{{ implode(' + ', $column['components']) }}" @endif
                                >{{ $column['name'] }}</th>
                            @endforeach
                            <th scope="col" class="px-2 py-2 text-center font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Prom.') }}</th>
                            <th scope="col" class="px-2 py-2 text-center font-medium">{{ __('Puesto') }}</th>
                            <th scope="col" class="px-2 py-2 text-center font-medium">{{ __('Pierde') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($consolidado['rows'] as $row)
                            <tr wire:key="row-{{ $row['id'] }}" class="group bg-white hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800/60">
                                <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-normal group-hover:bg-zinc-50 dark:bg-zinc-900 dark:group-hover:bg-zinc-800">
                                    <div class="font-medium text-zinc-900 lg:whitespace-nowrap dark:text-zinc-100">
                                        {{ $row['name'] }}
                                        @if ($consolidado['year'] && ! $row['took_previous'])
                                            <flux:badge size="sm" color="amber" class="ms-1 align-middle">{{ __('sin :level', ['level' => $parts['previous']]) }}</flux:badge>
                                        @endif
                                    </div>
                                    @if ($row['document'])
                                        <div class="text-xs text-zinc-500">{{ $row['document'] }}</div>
                                    @endif
                                </th>
                                @if ($consolidado['showGroup'])
                                    <td class="px-2 py-2 text-xs whitespace-nowrap text-zinc-500">{{ $row['group'] ?? '—' }}</td>
                                @endif
                                @foreach ($consolidado['columns'] as $column)
                                    @php $cell = $row['cells'][$column['name']] ?? null; @endphp
                                    <td @class([
                                        'px-2 py-2 text-center tabular-nums',
                                        'bg-red-50 font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300' => $cell && $cell['failed'],
                                        'italic text-zinc-400 dark:text-zinc-500' => $cell && ! $cell['complete'],
                                        'text-zinc-800 dark:text-zinc-200' => $cell && $cell['complete'] && ! $cell['failed'],
                                    ])>{{ $cell ? $num($cell['score']) : '' }}</td>
                                @endforeach
                                <td @class(['px-2 py-2 text-center font-semibold tabular-nums', 'text-red-600 dark:text-red-400' => $isFail($row['average']), 'text-zinc-900 dark:text-zinc-100' => ! $isFail($row['average'])])>{{ $num($row['average']) }}</td>
                                <td class="px-2 py-2 text-center tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row['rank'] ?? '—' }}</td>
                                <td @class(['px-2 py-2 text-center tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => $row['failed'] > 0, 'text-zinc-400' => $row['failed'] === 0])>{{ $row['failed'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 text-xs dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach (['average' => __('Promedio'), 'passed' => __('Aprueban'), 'failed' => __('Pierden')] as $key => $label)
                            <tr wire:key="stat-{{ $key }}">
                                <th scope="row" class="sticky left-0 z-10 bg-zinc-50 px-3 py-1.5 text-left font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $label }}</th>
                                @if ($consolidado['showGroup'])
                                    <td></td>
                                @endif
                                @foreach ($consolidado['columns'] as $column)
                                    @php $value = $consolidado['stats'][$column['name']][$key]; @endphp
                                    <td @class([
                                        'px-2 py-1.5 text-center font-semibold tabular-nums',
                                        'text-red-600 dark:text-red-400' => ($key === 'average' && $isFail($value)) || ($key === 'failed' && $value > 0),
                                        'text-zinc-700 dark:text-zinc-200' => ! (($key === 'average' && $isFail($value)) || ($key === 'failed' && $value > 0)),
                                    ])>{{ $key === 'average' ? $num($value) : $value }}</td>
                                @endforeach
                                <td class="px-2 py-1.5 text-center font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $key === 'average' ? $num($s['average']) : '' }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforeach
                    </tfoot>
                </table>
            </div>

            <p class="mt-2 text-xs text-zinc-500">
                {{ __('En rojo, por debajo de :score.', ['score' => number_format($passingScore, 1)]) }}
                @if ($s['pending_cells'] > 0 || $s['without_previous'] > 0)
                    {{ __('En gris y cursiva, notas incompletas.') }}
                @endif
                @if (collect($consolidado['columns'])->contains(fn ($column) => $column['components'] !== []))
                    {{ __('Las áreas promedian sus materias, como en el boletín.') }}
                @endif
                @if ($s['without_previous'] > 0)
                    {{ __('Quienes no cursaron :level van sin puesto y fuera de las cuentas del ciclo.', ['level' => $parts['previous']]) }}
                @endif
            </p>
        @endif
    @endif

    <x-tour name="consolidados" :autostart="$startTour" :steps="[
        ['target' => null, 'title' => __('Así se lee el consolidado'), 'body' => __('Las notas de todos los estudiantes de un ciclo en una tabla, con un resumen arriba. Puedes saltar esta guía y volver a verla con «¿Cómo funciona?».')],
        ['target' => 'term', 'title' => __('1. El semestre'), 'body' => __('Elige el año y el semestre. La pantalla abre en el más reciente.')],
        ['target' => 'cycles', 'title' => __('2. El ciclo'), 'body' => __('Cada tarjeta dice cómo está cada módulo. El consolidado es definitivo cuando los dos están cerrados; antes sale como provisional.')],
        ['target' => 'views', 'title' => __('Qué notas ver'), 'body' => __('«Definitiva» es el promedio de los dos módulos, la misma del boletín. Con «Módulo 1» o «Módulo 2» ves solo ese: sirve para leer el M1 apenas se cierre. Los ciclos 3 y 4 duran un año: en 3B y 4B se abre el año completo, con el promedio de los dos semestres. También puedes filtrar por grupo y ordenar por puesto.')],
        ['target' => 'summary', 'title' => __('La lectura rápida'), 'body' => __('El promedio del ciclo, cuántos aprueban todo, cuántos pierden 1, 2 o 3 materias o más, y las materias donde más se pierde.')],
        ['target' => 'dropped', 'title' => __('Los que no siguieron'), 'body' => __('En el año completo, aquí está la lista de quienes cursaron el primer semestre y no aparecen en el segundo, con su promedio de allá.')],
        ['target' => 'table', 'title' => __('Estudiante por estudiante'), 'body' => __('En rojo, las notas por debajo de 3.0; en gris, las que todavía están incompletas. Abajo, el promedio de cada materia y cuántos la aprueban y la pierden.')],
        ['target' => 'downloads', 'title' => __('Excel o PDF'), 'body' => __('Se descarga lo que estás viendo. El Excel trae filtros y una hoja con la nota de cada módulo; el PDF es para imprimir o archivar.')],
        ['target' => 'term-download', 'title' => __('Todo el semestre'), 'body' => __('Saca todos los ciclos juntos: en Excel, una hoja de resumen y una por ciclo; en PDF, una página por ciclo.')],
        ['target' => 'help', 'title' => __('¿Dudas? Vuelve aquí'), 'body' => __('Este botón abre otra vez esta guía.')],
    ]" />
</section>
