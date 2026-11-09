@php
    $pill = 'rounded-full px-3 py-1.5 text-sm font-medium transition';
    $pillOn = 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900';
    $pillOff = 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700';
@endphp

<section class="w-full">
    {{-- Encabezado --}}
    <div class="mb-8 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="pencil-square" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Editar notas') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Elige el semestre, el ciclo y la materia para ver o corregir sus notas.') }}</flux:subheading>
        </div>

        <x-tour-button name="editor-notas" />
    </div>

    {{-- 1 · Semestre --}}
    <div class="mb-8" data-tour="term">
        <div class="mb-3 flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">1</span>
            <flux:heading size="lg">{{ __('Semestre') }}</flux:heading>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="w-32">
                <flux:select wire:model.live="year" :label="__('Año')">
                    @foreach ($years as $option)
                        <flux:select.option :value="(string) $option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-40">
                <flux:select wire:model.live="semester" :label="__('Semestre')">
                    @foreach ($semesters as $option)
                        <flux:select.option :value="(string) $option">{{ __('Semestre :n', ['n' => $option]) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    {{-- 2 · Ciclo --}}
    <div class="mb-8">
        <div class="mb-3 flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">2</span>
            <flux:heading size="lg">{{ __('Ciclo') }}</flux:heading>
        </div>

        @if ($cycleCards->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-200 py-10 text-center dark:border-zinc-700">
                <flux:text class="text-zinc-400">{{ __('No hay ciclos en este semestre.') }}</flux:text>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-tour="cycles">
                @foreach ($cycleCards as $card)
                    @php $active = (string) $card['id'] === $cycleId; @endphp

                    <button
                        type="button"
                        wire:key="cycle-{{ $card['id'] }}"
                        wire:click="$set('cycleId', '{{ $card['id'] }}')"
                        aria-pressed="{{ $active ? 'true' : 'false' }}"
                        @class([
                            'flex flex-col gap-3 rounded-xl border bg-white p-4 text-left transition dark:bg-zinc-900',
                            'border-llano-jade ring-2 ring-llano-emerald/40 dark:border-llano-emerald' => $active,
                            'border-zinc-200 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:hover:border-zinc-600' => ! $active,
                        ])
                    >
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $card['name'] }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ trans_choice('{1}:count estudiante|[2,*]:count estudiantes', $card['students'], ['count' => $card['students']]) }}
                            </div>
                        </div>

                        {{-- Un sello por módulo: es lo que dice si el docente todavía puede escribir. --}}
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($card['modules'] as $n => $module)
                                <x-module-status :module="$n" :state="$module['state']" :at="$module['at']" />
                            @endforeach
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-xs text-zinc-500">
                                <span class="tabular-nums">{{ __(':graded de :total notas', ['graded' => $card['graded'], 'total' => $card['expected']]) }}</span>
                                <span class="tabular-nums font-medium text-zinc-700 dark:text-zinc-300">{{ $card['percent'] }}%</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div
                                    @class([
                                        'h-full rounded-full transition-all',
                                        'bg-emerald-500' => $card['percent'] === 100,
                                        'bg-amber-500' => $card['percent'] < 100 && $card['percent'] > 0,
                                        'bg-zinc-300 dark:bg-zinc-600' => $card['percent'] === 0,
                                    ])
                                    style="width: {{ $card['percent'] }}%"
                                ></div>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 3 · Materia --}}
    @if ($selected)
        <div>
            <div class="mb-3 flex items-center gap-2">
                <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">3</span>
                <flux:heading size="lg">{{ __('Materias de :cycle', ['cycle' => $selected->name]) }}</flux:heading>
            </div>

            <div class="mb-5 flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-3 lg:flex-row lg:flex-wrap lg:items-center dark:border-zinc-700 dark:bg-zinc-800/30" data-tour="course-filters">
                @if ($modules->count() > 1)
                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('Módulo') }}">
                        <button type="button" wire:click="$set('period', '')" @class([$pill, $pillOn => $period === '', $pillOff => $period !== ''])>{{ __('Todos los módulos') }}</button>
                        @foreach ($modules as $module)
                            <button
                                type="button"
                                wire:key="module-{{ $module }}"
                                wire:click="$set('period', '{{ $module }}')"
                                @class([$pill, $pillOn => $period === (string) $module, $pillOff => $period !== (string) $module])
                            >{{ __('Módulo :n', ['n' => $module]) }}</button>
                        @endforeach
                    </div>
                @endif

                @if ($groups->count() > 1)
                    <div class="w-full lg:w-44">
                        <flux:select wire:model.live="groupId" size="sm" :aria-label="__('Grupo')">
                            <flux:select.option value="">{{ __('Todos los grupos') }}</flux:select.option>
                            @foreach ($groups as $group)
                                <flux:select.option :value="(string) $group->id">{{ $group->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                <div class="w-full lg:w-64">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        size="sm"
                        :placeholder="__('Buscar materia o docente')"
                        clearable
                    />
                </div>

                <div class="flex flex-wrap gap-1.5 lg:ms-auto" role="group" aria-label="{{ __('Estado') }}">
                    <button type="button" wire:click="$set('status', '')" @class([$pill, $pillOn => $status === '', $pillOff => $status !== ''])>
                        {{ __('Todas (:n)', ['n' => $counts['all']]) }}
                    </button>
                    <button type="button" wire:click="$set('status', 'pendientes')" @class([$pill, 'bg-amber-500 text-white' => $status === 'pendientes', $pillOff => $status !== 'pendientes'])>
                        {{ __('Con pendientes (:n)', ['n' => $counts['pending']]) }}
                    </button>
                    <button type="button" wire:click="$set('status', 'completas')" @class([$pill, 'bg-emerald-600 text-white' => $status === 'completas', $pillOff => $status !== 'completas'])>
                        {{ __('Completas (:n)', ['n' => $counts['complete']]) }}
                    </button>
                </div>
            </div>

            @forelse ($byModule as $module => $cards)
                <div wire:key="module-block-{{ $module }}" class="mb-8">
                    <div class="mb-3 flex items-baseline gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ __('Módulo :n', ['n' => $module]) }}</h3>
                        <span class="text-xs text-zinc-400">
                            {{ trans_choice('{1}:count materia|[2,*]:count materias', $cards->count(), ['count' => $cards->count()]) }}
                        </span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($cards as $card)
                            @php
                                $tone = match ($card['status']) {
                                    'completo' => ['dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500', 'label' => __('Completa')],
                                    'parcial' => ['dot' => 'bg-amber-500', 'bar' => 'bg-amber-500', 'label' => __('Faltan :n', ['n' => $card['pending']])],
                                    'pendiente' => ['dot' => 'bg-zinc-400', 'bar' => 'bg-zinc-400', 'label' => __('Sin empezar')],
                                    default => ['dot' => 'bg-zinc-300', 'bar' => 'bg-zinc-300', 'label' => __('Sin estudiantes')],
                                };
                            @endphp

                            <a
                                href="{{ route('admin.grade-editor.course', $card['id']) }}"
                                wire:navigate
                                wire:key="course-{{ $card['id'] }}"
                                @if ($loop->parent->first && $loop->first) data-tour="course" @endif
                                class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-llano-emerald dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $card['subject'] }}</div>
                                        <div @class([
                                            'mt-0.5 flex items-center gap-1 truncate text-sm',
                                            'text-zinc-500' => $card['teacher'],
                                            'text-amber-600 dark:text-amber-400' => ! $card['teacher'],
                                        ])>
                                            <flux:icon name="user" variant="micro" class="size-3.5 shrink-0" />
                                            <span class="truncate">{{ $card['teacher'] ?? __('Sin docente asignado') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        @if ($card['group'])
                                            <flux:badge size="sm" color="zinc" variant="outline">{{ $card['group'] }}</flux:badge>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-1.5 flex items-baseline justify-between text-sm">
                                        <span class="tabular-nums text-zinc-600 dark:text-zinc-400">
                                            {{ __(':graded de :total notas', ['graded' => $card['graded'], 'total' => $card['students']]) }}
                                        </span>
                                        <span class="text-zinc-500">
                                            {{ __('Prom.') }}
                                            <span class="tabular-nums font-medium text-zinc-900 dark:text-zinc-100">{{ $card['average'] !== null ? number_format($card['average'], 2) : '—' }}</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full transition-all {{ $tone['bar'] }}" style="width: {{ $card['percent'] }}%"></div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 pt-3 text-xs dark:border-zinc-800">
                                    <span class="flex items-center gap-1.5 text-zinc-500">
                                        <span class="size-2 rounded-full {{ $tone['dot'] }}"></span>
                                        {{ $tone['label'] }}
                                    </span>

                                    {{-- Lo que importa aquí es si el docente todavía puede: la coordinación siempre puede. --}}
                                    @if (! $card['teacher_open'])
                                        <span class="flex items-center gap-1 text-zinc-400">
                                            <flux:icon name="lock-closed" variant="micro" class="size-3.5" />
                                            {{ __('Cerrado para el docente') }}
                                        </span>
                                    @elseif ($card['teacher_until'])
                                        <span class="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                            <flux:icon name="clock" variant="micro" class="size-3.5" />
                                            {{ __('Docente hasta el :date', ['date' => $card['teacher_until']->translatedFormat('j \d\e M')]) }}
                                        </span>
                                    @endif

                                    <span class="flex items-center gap-0.5 font-medium text-llano-jade dark:text-llano-emerald">
                                        {{ __('Abrir') }}
                                        <flux:icon name="arrow-right" variant="micro" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-200 py-14 text-center dark:border-zinc-700">
                    <flux:icon name="funnel" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-400">
                        {{ $filtering ? __('Ninguna materia coincide con los filtros.') : __('Este ciclo todavía no tiene materias.') }}
                    </flux:text>
                    @if ($filtering)
                        <flux:button size="sm" variant="ghost" class="mt-3" wire:click="clearFilters">{{ __('Limpiar filtros') }}</flux:button>
                    @endif
                </div>
            @endforelse
        </div>
    @endif

    <x-tour name="editor-notas" :autostart="$startTour" :steps="[
        ['target' => null, 'title' => __('Así se editan las notas'), 'body' => __('En tres pasos llegas a la planilla de cualquier curso. Puedes saltar esta guía y volver a verla con «¿Cómo funciona?».')],
        ['target' => 'term', 'title' => __('1. El semestre'), 'body' => __('Elige el año y el semestre. La pantalla abre en el más reciente.')],
        ['target' => 'cycles', 'title' => __('2. El ciclo'), 'body' => __('Cada tarjeta muestra cuántas notas van puestas y cómo está cada módulo para los docentes: M1 y M2 abiertos, cerrados o con fecha de cierre. Haz clic para cambiar de ciclo.')],
        ['target' => 'course-filters', 'title' => __('Filtra las materias'), 'body' => __('Por módulo, por grupo o por el nombre de la materia o del docente. «Con pendientes» deja solo las que todavía tienen notas por poner.')],
        ['target' => 'course', 'title' => __('3. Abre la planilla'), 'body' => __('La tarjeta dice quién dicta la materia, cuánto lleva y si el docente todavía puede editar. Haz clic para ver y corregir las notas: tú puedes editar aunque el semestre esté cerrado.')],
        ['target' => 'help', 'title' => __('¿Dudas? Vuelve aquí'), 'body' => __('Este botón abre otra vez esta guía.')],
    ]" />
</section>
