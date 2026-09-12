<section class="w-full">
    {{-- Encabezado --}}
    <div class="mb-8">
        <flux:heading size="xl" level="1">{{ __('Hola, :name', ['name' => $teacher->first_name]) }}</flux:heading>
        <flux:subheading>{{ __('Estas son las materias que tienes a cargo.') }}</flux:subheading>
    </div>

    {{-- Resumen del semestre --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Materias') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $summary['courses'] }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Estudiantes') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $summary['students'] }}</div>
        </div>

        <div @class([
            'rounded-xl border p-4',
            'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' => $summary['pending'] > 0,
            'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => $summary['pending'] === 0,
        ])>
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Notas por poner') }}</div>
            <div @class([
                'mt-1 text-2xl font-semibold tabular-nums',
                'text-amber-700 dark:text-amber-300' => $summary['pending'] > 0,
                'text-zinc-900 dark:text-zinc-100' => $summary['pending'] === 0,
            ])>{{ $summary['pending'] }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Promedio general') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                {{ $summary['average'] !== null ? number_format($summary['average'], 2) : '—' }}
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        @if ($cycles->count() > 1)
            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    wire:click="$set('cycleId', '')"
                    @class([
                        'rounded-full px-3 py-1.5 text-sm font-medium transition',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $cycleId === '',
                        'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $cycleId !== '',
                    ])
                >{{ __('Todos') }}</button>

                @foreach ($cycles as $cycle)
                    <button
                        type="button"
                        wire:key="cycle-{{ $cycle->id }}"
                        wire:click="$set('cycleId', '{{ $cycle->id }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-sm font-medium transition',
                            'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $cycleId === (string) $cycle->id,
                            'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $cycleId !== (string) $cycle->id,
                        ])
                    >{{ $cycle->name }}</button>
                @endforeach
            </div>
        @endif

        <div class="w-full sm:ms-auto sm:w-64">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                size="sm"
                :placeholder="__('Buscar materia o grupo')"
                clearable
            />
        </div>
    </div>

    {{-- Materias --}}
    @if ($cards->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <flux:icon name="book-open" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">
                @if ($search !== '' || $cycleId !== '')
                    {{ __('Ninguna materia coincide con la búsqueda.') }}
                @else
                    {{ __('Todavía no tienes materias asignadas. Comunícate con la coordinación académica.') }}
                @endif
            </flux:text>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                @php
                    $tone = match ($card['status']) {
                        'completo' => ['dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500', 'label' => __('Completa')],
                        'parcial' => ['dot' => 'bg-amber-500', 'bar' => 'bg-amber-500', 'label' => __('En progreso')],
                        'pendiente' => ['dot' => 'bg-zinc-400', 'bar' => 'bg-zinc-400', 'label' => __('Sin empezar')],
                        default => ['dot' => 'bg-zinc-300', 'bar' => 'bg-zinc-300', 'label' => __('Sin estudiantes')],
                    };
                @endphp

                <a
                    href="{{ route('teacher.gradebook', $card['id']) }}"
                    wire:navigate
                    wire:key="course-{{ $card['id'] }}"
                    class="group flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <flux:badge size="sm" color="zinc">{{ __('Periodo :n', ['n' => $card['period']]) }}</flux:badge>
                            @if ($card['group'] !== '')
                                <flux:badge size="sm" color="zinc" variant="outline">{{ $card['group'] }}</flux:badge>
                            @endif
                        </div>

                        <span class="flex shrink-0 items-center gap-1.5 text-xs text-zinc-500">
                            <span class="size-2 rounded-full {{ $tone['dot'] }}"></span>
                            {{ $tone['label'] }}
                        </span>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold leading-snug text-zinc-900 dark:text-zinc-100">{{ $card['subject'] }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-500">{{ $card['cycle'] }}</p>
                    </div>

                    <div class="mt-auto">
                        <div class="mb-1.5 flex items-baseline justify-between text-sm">
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ __(':graded de :total notas', ['graded' => $card['graded'], 'total' => $card['students']]) }}
                            </span>
                            <span class="tabular-nums font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $card['average'] !== null ? number_format($card['average'], 2) : '—' }}
                            </span>
                        </div>

                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full transition-all {{ $tone['bar'] }}" style="width: {{ $card['percent'] }}%"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                        {{ $card['pending'] > 0 ? __('Poner notas') : __('Revisar notas') }}
                        <flux:icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-0.5" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
