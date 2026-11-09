@props(['roster', 'rosterTotal', 'groups', 'studentId', 'search' => '', 'groupId' => ''])

{{-- La lista de estudiantes del ciclo para Boletines y Certificados, con
     buscador por nombre o documento y filtro de grupo. Cada fila dice cuántas
     notas tiene de las que le tocan. Escribe en studentId, search y groupId
     del componente de Livewire. --}}

<div {{ $attributes->class('flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 print:hidden') }} data-tour="students">
    <div class="space-y-2 border-b border-zinc-100 p-3 dark:border-zinc-800">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            size="sm"
            :placeholder="__('Buscar por nombre o documento')"
            :aria-label="__('Buscar estudiante')"
            clearable
        />

        @if ($groups->count() > 1)
            <flux:select wire:model.live="groupId" size="sm" :aria-label="__('Grupo')">
                <flux:select.option value="">{{ __('Todos los grupos') }}</flux:select.option>
                @foreach ($groups as $id => $name)
                    <flux:select.option :value="(string) $id">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="px-1 text-xs text-zinc-500">
            @if ($roster->count() === $rosterTotal)
                {{ trans_choice('{0}Sin estudiantes matriculados|{1}1 estudiante|[2,*]:count estudiantes', $rosterTotal, ['count' => $rosterTotal]) }}
            @else
                {{ __(':shown de :total estudiantes', ['shown' => $roster->count(), 'total' => $rosterTotal]) }}
            @endif
        </div>
    </div>

    <ul class="max-h-80 divide-y divide-zinc-100 overflow-y-auto lg:max-h-[38rem] dark:divide-zinc-800">
        @forelse ($roster as $row)
            @php
                $active = (string) $row['id'] === $studentId;
                $complete = $row['expected'] > 0 && $row['graded'] >= $row['expected'];
            @endphp

            <li wire:key="student-{{ $row['id'] }}">
                <button
                    type="button"
                    wire:click="$set('studentId', '{{ $row['id'] }}')"
                    aria-pressed="{{ $active ? 'true' : 'false' }}"
                    @class([
                        'flex w-full items-center gap-3 px-3 py-2.5 text-left transition',
                        'bg-llano-emerald/10 dark:bg-llano-emerald/15' => $active,
                        'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => ! $active,
                    ])
                >
                    <span @class([
                        'w-1 self-stretch rounded-full',
                        'bg-llano-jade dark:bg-llano-emerald' => $active,
                        'bg-transparent' => ! $active,
                    ])></span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $row['name'] }}</span>
                        <span class="block truncate text-xs text-zinc-500">
                            {{ $row['document'] ?? __('Sin documento') }}@if ($row['group'] && $groups->count() > 1) · {{ $row['group'] }}@endif
                        </span>
                    </span>

                    @if ($row['expected'] > 0)
                        <span
                            @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium tabular-nums',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $complete,
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => ! $complete,
                            ])
                            title="{{ __(':graded de :total notas puestas', ['graded' => $row['graded'], 'total' => $row['expected']]) }}"
                        >{{ $row['graded'] }}/{{ $row['expected'] }}</span>
                    @endif
                </button>
            </li>
        @empty
            <li class="px-4 py-10 text-center text-sm text-zinc-400">
                {{ $search !== '' || $groupId !== '' ? __('Nadie coincide con la búsqueda.') : __('Este ciclo no tiene estudiantes matriculados.') }}
                @if ($search !== '' || $groupId !== '')
                    <div class="mt-2">
                        <flux:button wire:click="clearSearch" size="sm" variant="ghost">{{ __('Limpiar búsqueda') }}</flux:button>
                    </div>
                @endif
            </li>
        @endforelse
    </ul>
</div>
