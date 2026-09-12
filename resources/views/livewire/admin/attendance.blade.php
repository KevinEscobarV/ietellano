<section class="w-full">
    <div class="mb-6">
        <div class="mb-1 flex items-center gap-2">
            <flux:icon name="clipboard-document-check" class="size-6 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="xl" level="1">{{ __('Asistencia') }}</flux:heading>
        </div>
        <flux:subheading>
            {{ __('Fallas acumuladas por materia. Se pierde la materia al pasar del :rate% de inasistencias sin justificar.', ['rate' => round($maxRate * 100)]) }}
        </flux:subheading>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="cycleId" :label="__('Ciclo')" size="sm" class="w-56">
            @foreach ($cycles as $cycle)
                <flux:select.option :value="$cycle->id">{{ $cycle->label }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($groups->count() > 1)
            <flux:select wire:model.live="groupId" :label="__('Grupo')" size="sm" class="w-44">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                @foreach ($groups as $group)
                    <flux:select.option :value="$group->id">{{ $group->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            size="sm"
            class="w-64"
            :placeholder="__('Buscar estudiante o documento')"
        />

        <flux:checkbox wire:model.live="onlyAtRisk" :label="__('Solo en riesgo')" />

        <flux:button wire:click="export" size="sm" icon="arrow-down-tray" class="ms-auto">
            {{ __('Exportar CSV') }}
        </flux:button>
    </div>

    @if ($subjects->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-400">{{ __('Este ciclo no tiene cursos.') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <th class="sticky start-0 bg-white p-3 text-start font-semibold dark:bg-zinc-900">{{ __('Estudiante') }}</th>
                        @foreach ($subjects as $subject)
                            <th class="p-3 text-center font-semibold" title="{{ $subject->code }}">{{ $subject->name }}</th>
                        @endforeach
                        <th class="p-3 text-center font-semibold">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($rows as $row)
                        <tr wire:key="row-{{ $row['id'] }}">
                            <td class="sticky start-0 bg-white p-3 dark:bg-zinc-900">
                                <div class="font-medium whitespace-nowrap text-zinc-800 dark:text-zinc-200">
                                    {{ $row['last_name'] }} {{ $row['first_name'] }}
                                </div>
                                <div class="text-xs text-zinc-500">
                                    {{ $row['document'] }}@if ($row['group']) · {{ $row['group'] }}@endif
                                </div>
                            </td>

                            @foreach ($subjects as $subject)
                                @php $line = $row['subjects'][$subject->id] ?? null; @endphp
                                <td class="p-3 text-center tabular-nums">
                                    @if ($line === null || $line['held'] === 0)
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @elseif ($line['lost'])
                                        <span
                                            class="inline-block rounded bg-rose-100 px-1.5 py-0.5 font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300"
                                            title="{{ __(':absences de :held clases · :rate%', ['absences' => $line['absences'], 'held' => $line['held'], 'rate' => round($line['rate'] * 100)]) }}"
                                        >{{ $line['absences'] }}</span>
                                    @elseif ($line['absences'] > 0)
                                        <span title="{{ __(':absences de :held clases', ['absences' => $line['absences'], 'held' => $line['held']]) }}">{{ $line['absences'] }}</span>
                                    @else
                                        <span class="text-zinc-400">0</span>
                                    @endif
                                </td>
                            @endforeach

                            <td @class([
                                'p-3 text-center font-semibold tabular-nums',
                                'text-rose-600 dark:text-rose-400' => $row['lost'] > 0,
                            ])>{{ $row['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $subjects->count() + 2 }}" class="p-8 text-center text-sm text-zinc-400">
                                {{ $onlyAtRisk ? __('Nadie pierde materia por inasistencia.') : __('No hay estudiantes matriculados.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</section>
