@php
    use App\Services\BoletinService;

    /**
     * Paleta por desempeño (Decreto 1290). El color del campo y el chip dicen
     * lo mismo, para que la planilla se lea de un vistazo sin tener que
     * comparar números.
     */
    $toneFor = function (?float $score): array {
        if ($score === null) {
            return [
                'field' => 'border-zinc-300 focus:border-emerald-500 focus:ring-emerald-500/30 dark:border-zinc-600',
                'chip' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
                'label' => __('Sin nota'),
            ];
        }

        return match (BoletinService::performance($score)) {
            'Superior' => [
                'field' => 'border-emerald-400 bg-emerald-50 text-emerald-800 focus:border-emerald-500 focus:ring-emerald-500/30 dark:border-emerald-500/50 dark:bg-emerald-500/10 dark:text-emerald-300',
                'chip' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                'label' => __('Superior'),
            ],
            'Alto' => [
                'field' => 'border-emerald-300 bg-emerald-50/60 text-emerald-800 focus:border-emerald-500 focus:ring-emerald-500/30 dark:border-emerald-500/40 dark:bg-emerald-500/5 dark:text-emerald-300',
                'chip' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                'label' => __('Alto'),
            ],
            'Básico' => [
                'field' => 'border-amber-300 bg-amber-50 text-amber-900 focus:border-amber-500 focus:ring-amber-500/30 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200',
                'chip' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
                'label' => __('Básico'),
            ],
            default => [
                'field' => 'border-red-300 bg-red-50 text-red-800 focus:border-red-500 focus:ring-red-500/30 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300',
                'chip' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                'label' => __('Bajo'),
            ],
        };
    };
@endphp

{{-- Columna angosta a propósito: en una planilla el nombre y su casilla
     tienen que quedar cerca, no en extremos opuestos de la pantalla. --}}
<section class="w-full max-w-4xl">
    {{-- Encabezado --}}
    <div class="mb-6">
        <flux:link :href="route('teacher.courses')" wire:navigate variant="subtle" class="mb-3 inline-flex items-center gap-1 text-sm">
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Mis materias') }}
        </flux:link>

        <flux:heading size="xl" level="1">{{ $course->subject?->name ?? $course->code }}</flux:heading>

        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <flux:badge size="sm" color="zinc">{{ __('Periodo :n', ['n' => $course->period]) }}</flux:badge>
            @if ($course->group)
                <flux:badge size="sm" color="zinc" variant="outline">{{ $course->group->name }}</flux:badge>
            @endif
            @if ($course->cycle)
                <flux:badge size="sm" color="zinc" variant="outline">{{ $course->cycle->label }}</flux:badge>
            @endif
            <flux:badge size="sm" color="zinc" variant="outline">{{ $course->code }}</flux:badge>
        </div>
    </div>

    <x-term-notice :editable="$this->editable" :until="$this->openUntil" />

    {{-- Barra de avance: se queda arriba mientras el docente baja por la lista --}}
    <div
        x-data="{ saved: false, timer: null }"
        @grade-saved.window="saved = true; clearTimeout(timer); timer = setTimeout(() => saved = false, 2200)"
        class="sticky top-0 z-10 mb-4 rounded-xl border border-zinc-200 bg-white/95 p-4 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95"
    >
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                <span class="tabular-nums font-medium text-zinc-900 dark:text-zinc-100">
                    {{ __(':graded de :total', ['graded' => $summary['graded'], 'total' => $summary['total']]) }}
                    <span class="font-normal text-zinc-500">{{ __('notas') }}</span>
                </span>

                <span class="text-zinc-500">
                    {{ __('Promedio') }}
                    <span class="tabular-nums font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $summary['average'] !== null ? number_format($summary['average'], 2) : '—' }}
                    </span>
                </span>

                <span class="flex items-center gap-1.5 text-zinc-500">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    {{ trans_choice('{1}:count aprueba|[2,*]:count aprueban', $summary['passing'], ['count' => $summary['passing']]) }}
                </span>

                <span class="flex items-center gap-1.5 text-zinc-500">
                    <span class="size-2 rounded-full bg-red-500"></span>
                    {{ trans_choice('{1}:count reprueba|[2,*]:count reprueban', $summary['failing'], ['count' => $summary['failing']]) }}
                </span>
            </div>

            <div class="flex items-center gap-3">
                <span wire:loading.delay class="flex items-center gap-1.5 text-sm text-zinc-500">
                    <flux:icon name="loading" class="size-4" />
                    {{ __('Guardando…') }}
                </span>

                <span
                    wire:loading.remove.delay
                    x-show="saved"
                    x-transition.opacity
                    x-cloak
                    class="flex items-center gap-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                >
                    <flux:icon name="check-circle" class="size-4" />
                    {{ __('Guardado') }}
                </span>

                @if ($this->editable)
                    <flux:button size="sm" variant="primary" icon="check" wire:click="saveAll" wire:loading.attr="disabled">
                        {{ __('Guardar todo') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div
                @class([
                    'h-full rounded-full transition-all duration-500',
                    'bg-emerald-500' => $summary['pending'] === 0,
                    'bg-amber-500' => $summary['pending'] > 0,
                ])
                style="width: {{ $summary['percent'] }}%"
            ></div>
        </div>
    </div>

    {{-- Filtros de la lista --}}
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-72">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                size="sm"
                :placeholder="__('Buscar estudiante o documento')"
                clearable
            />
        </div>

        @if ($summary['pending'] > 0)
            <button
                type="button"
                wire:click="$toggle('onlyPending')"
                @class([
                    'rounded-full px-3 py-1.5 text-sm font-medium transition',
                    'bg-amber-500 text-white' => $onlyPending,
                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => ! $onlyPending,
                ])
            >
                {{ __('Solo pendientes (:n)', ['n' => $summary['pending']]) }}
            </button>
        @endif

        @if ($this->editable)
            <flux:text size="sm" class="w-full text-zinc-400 sm:ms-auto sm:w-auto">
                {{ __('Enter o ↓ pasa al siguiente estudiante') }}
            </flux:text>
        @endif
    </div>

    {{-- Planilla --}}
    <div
        data-roster
        x-data="{
            move(event, delta) {
                const scope = event.target.closest('[data-roster]');

                if (! scope || ! event.target.matches('input[data-score]')) {
                    return;
                }

                const inputs = Array.from(scope.querySelectorAll('input[data-score]'));
                const index = inputs.indexOf(event.target);

                if (index === -1) {
                    return;
                }

                event.preventDefault();

                const next = inputs[index + delta];

                if (next) {
                    next.focus();
                    next.select();
                }
            },
        }"
        @keydown.enter="move($event, 1)"
        @keydown.down="move($event, 1)"
        @keydown.up="move($event, -1)"
        class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700"
    >
        @forelse ($students as $student)
            @php
                $raw = $scores[$student->id] ?? null;
                $value = ($raw === null || $raw === '') ? null : (float) $raw;
                $tone = $toneFor($value);
            @endphp

            <div
                wire:key="student-{{ $student->id }}"
                class="flex items-center gap-3 bg-white p-3 transition focus-within:bg-zinc-50 sm:gap-4 sm:p-4 dark:bg-zinc-900 dark:focus-within:bg-zinc-800/50"
            >
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    {{ mb_substr($student->last_name, 0, 1).mb_substr($student->first_name, 0, 1) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $student->last_name }} {{ $student->first_name }}
                    </div>
                    <div class="truncate text-sm text-zinc-500">{{ $student->document ?? '—' }}</div>
                </div>

                <span class="hidden shrink-0 rounded-full px-2.5 py-1 text-xs font-medium sm:inline-block {{ $tone['chip'] }}">
                    {{ $tone['label'] }}
                </span>

                <div class="w-20 shrink-0 sm:w-24">
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="5"
                        inputmode="decimal"
                        data-score
                        wire:model.blur="scores.{{ $student->id }}"
                        @readonly(! $this->editable)
                        placeholder="—"
                        aria-label="{{ __('Nota de :name', ['name' => $student->fullName()]) }}"
                        class="w-full rounded-lg border bg-white px-2 py-2 text-center text-lg font-semibold tabular-nums transition focus:outline-none focus:ring-2 read-only:cursor-default read-only:opacity-75 dark:bg-zinc-900 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none {{ $tone['field'] }}"
                    >

                    @error('scores.'.$student->id)
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @empty
            <div class="bg-white py-16 text-center dark:bg-zinc-900">
                <flux:icon name="users" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-400">
                    @if ($search !== '' || $onlyPending)
                        {{ __('Ningún estudiante coincide con el filtro.') }}
                    @else
                        {{ __('Esta materia todavía no tiene estudiantes matriculados.') }}
                    @endif
                </flux:text>
            </div>
        @endforelse
    </div>

    @if ($students->isNotEmpty())
        <div class="mt-4 flex items-center justify-between gap-4">
            <flux:text size="sm" class="text-zinc-400">
                {{ $this->editable
                    ? __('Cada nota se guarda sola al salir de la casilla.')
                    : __('Planilla en solo lectura.') }}
            </flux:text>

            @if ($this->editable)
                <flux:button variant="primary" icon="check" wire:click="saveAll" wire:loading.attr="disabled">
                    {{ __('Guardar todo') }}
                </flux:button>
            @endif
        </div>
    @endif
</section>
