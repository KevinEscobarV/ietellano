@php
    use App\Enums\AttendanceStatus;

    $card = 'rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
    $label = 'text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400';

    // El estado se lee de un vistazo por el color, igual que la escala de notas
    // en la planilla: verde estuvo, ámbar justificó, rojo faltó.
    $tones = [
        AttendanceStatus::Present->value => [
            'on' => 'bg-emerald-600 text-white shadow-sm',
            'off' => 'text-zinc-500 hover:bg-emerald-50 dark:hover:bg-emerald-500/10',
        ],
        AttendanceStatus::Excused->value => [
            'on' => 'bg-amber-500 text-white shadow-sm',
            'off' => 'text-zinc-500 hover:bg-amber-50 dark:hover:bg-amber-500/10',
        ],
        AttendanceStatus::Absent->value => [
            'on' => 'bg-rose-600 text-white shadow-sm',
            'off' => 'text-zinc-500 hover:bg-rose-50 dark:hover:bg-rose-500/10',
        ],
    ];
@endphp

<section class="w-full max-w-4xl">
    {{-- Encabezado --}}
    <div class="mb-6">
        <flux:button :href="route('teacher.courses')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="-ms-2 mb-3">
            {{ __('Mis materias') }}
        </flux:button>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $course->subject?->name ?? $course->code }}</flux:heading>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <flux:badge size="sm" color="zinc" variant="outline">{{ $course->cycle->label }}</flux:badge>
                    @if ($course->group)
                        <flux:badge size="sm" color="zinc" variant="outline">{{ $course->group->name }}</flux:badge>
                    @endif
                    <flux:badge size="sm" color="zinc" variant="outline">{{ __('Periodo :n', ['n' => $course->period]) }}</flux:badge>
                </div>
            </div>

            <flux:button :href="route('teacher.gradebook', $course)" wire:navigate size="sm" icon="pencil-square">
                {{ __('Poner notas') }}
            </flux:button>
        </div>
    </div>

    <x-term-notice :editable="$this->editable" :until="$this->openUntil" />

    @if ($sessions->isEmpty())
        <div class="{{ $card }} p-8 text-center">
            <flux:heading size="lg">{{ __('Este ciclo todavía no tiene calendario') }}</flux:heading>
            <flux:subheading class="mt-2">
                {{ __('Un administrador tiene que generar los días de clase del ciclo antes de que puedas pasar asistencia.') }}
            </flux:subheading>
        </div>
    @else
        {{-- Selección del día y resumen --}}
        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="{{ $card }} p-4 sm:col-span-2">
                <label for="sesion" class="{{ $label }}">{{ __('Día de clase') }}</label>
                <flux:select id="sesion" wire:model.live="sessionId" size="sm" class="mt-1.5">
                    @foreach ($sessions as $session)
                        <flux:select.option :value="$session->id">
                            {{ $session->date->translatedFormat('l j \d\e F \d\e Y') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <p class="mt-2 text-xs text-zinc-500">
                    {{ __('Asistencia pasada en :held de :total días', ['held' => $progress['held'], 'total' => $progress['total']]) }}
                </p>
                @unless ($this->recorded)
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        {{ __('Este día todavía no tiene asistencia registrada.') }}
                    </p>
                @endunless
            </div>

            <div class="{{ $card }} p-4">
                <div class="{{ $label }}">{{ __('Presentes') }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                    @if ($this->editable || $this->recorded)
                        {{ $summary['present'] }} <span class="text-base font-normal text-zinc-400">/ {{ $summary['total'] }}</span>
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </div>
            </div>

            <div @class([
                'p-4 rounded-xl border',
                'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10' => $summary['absent'] > 0,
                'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => $summary['absent'] === 0,
            ])>
                <div class="{{ $label }}">{{ __('Fallaron') }}</div>
                <div @class([
                    'mt-1 text-2xl font-semibold tabular-nums',
                    'text-rose-700 dark:text-rose-300' => $summary['absent'] > 0,
                    'text-zinc-900 dark:text-zinc-100' => $summary['absent'] === 0,
                ])>
                    @if ($this->editable || $this->recorded)
                        {{ $summary['absent'] }}
                        @if ($summary['excused'] > 0)
                            <span class="text-base font-normal text-amber-600 dark:text-amber-400">+{{ $summary['excused'] }} just.</span>
                        @endif
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Barra de acciones --}}
        <div
            class="mb-4 flex flex-wrap items-center gap-3"
            x-data="{ saved: false, timer: null }"
            @attendance-saved.window="saved = true; clearTimeout(timer); timer = setTimeout(() => saved = false, 1800)"
        >
            @if ($this->editable)
                <flux:button wire:click="markAllPresent" size="sm" icon="check" variant="ghost">
                    {{ __('Todos presentes') }}
                </flux:button>
            @endif

            <span x-show="saved" x-transition.opacity class="text-sm text-emerald-600 dark:text-emerald-400" x-cloak>
                {{ __('Guardado') }}
            </span>

            <div class="w-full sm:ms-auto sm:w-64">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    size="sm"
                    :placeholder="__('Buscar estudiante')"
                />
            </div>
        </div>

        {{-- Lista --}}
        <div class="{{ $card }} divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($students as $student)
                @php
                    $current = $status[$student->id] ?? AttendanceStatus::Present->value;
                    $total = $totals[$student->id] ?? null;
                @endphp

                <div wire:key="student-{{ $student->id }}" class="flex flex-wrap items-center justify-between gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $student->last_name }} {{ $student->first_name }}
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            <span>{{ $student->document }}</span>
                            @if ($total && $total['absences'] > 0)
                                <span @class([
                                    'rounded-full px-1.5 py-0.5 font-medium tabular-nums',
                                    'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' => $total['lost'],
                                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $total['lost'],
                                ])>
                                    {{ trans_choice('{1}lleva :count falla|[2,*]lleva :count fallas', $total['absences'], ['count' => $total['absences']]) }}
                                    @if ($total['rate'] !== null)
                                        · {{ round($total['rate'] * 100) }}%
                                    @endif
                                </span>
                            @endif
                            @if ($total && $total['lost'])
                                <flux:badge size="sm" color="rose">{{ __('Pierde por inasistencia') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    @if (! $this->editable && ! $this->recorded)
                        {{-- Nadie pasó lista ese día: el verde de "Presente" sería
                             un registro que no existe. --}}
                        <span class="shrink-0 text-sm text-zinc-400">{{ __('Sin registrar') }}</span>
                    @else
                        {{-- Tres estados, un clic. El radio va oculto pero sigue siendo lo que se marca. --}}
                        <fieldset class="flex shrink-0 rounded-lg bg-zinc-100 p-0.5 dark:bg-zinc-800">
                            <legend class="sr-only">{{ __('Asistencia de :name', ['name' => $student->first_name]) }}</legend>

                            @foreach (AttendanceStatus::cases() as $case)
                                <label @class([
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                    'cursor-pointer' => $this->editable,
                                    'cursor-default' => ! $this->editable,
                                    $tones[$case->value][$current === $case->value ? 'on' : 'off'],
                                ])>
                                    <input
                                        type="radio"
                                        class="sr-only"
                                        wire:model.live="status.{{ $student->id }}"
                                        value="{{ $case->value }}"
                                        @disabled(! $this->editable)
                                    >
                                    {{ $case->label() }}
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-sm text-zinc-400">{{ __('Nadie coincide con la búsqueda.') }}</div>
            @endforelse
        </div>
    @endif
</section>
