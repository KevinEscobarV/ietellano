<div class="w-full">
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Configuración --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-1">{{ __('Días de clase') }}</flux:heading>
                <flux:subheading class="mb-5">
                    {{ __('El sistema genera un día de clase por semana entre las dos fechas. Los festivos se quitan después, uno a uno.') }}
                </flux:subheading>

                <div class="space-y-4">
                    <flux:select wire:model.live="cycleId" :label="__('Ciclo')">
                        @foreach ($cycles as $option)
                            <flux:select.option :value="$option->id">{{ $option->label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="class_weekday" :label="__('Día de la semana')" :placeholder="__('Elegir día')">
                        @foreach ($weekdays as $value => $name)
                            <flux:select.option :value="$value">{{ __($name) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="starts_on" type="date" :label="__('Primer día')" />
                        <flux:input wire:model="ends_on" type="date" :label="__('Último día')" />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="generate" variant="primary" size="sm" icon="calendar-days">
                            {{ __('Generar calendario') }}
                        </flux:button>
                        <flux:button wire:click="save" variant="ghost" size="sm">{{ __('Solo guardar') }}</flux:button>
                    </div>
                </div>

                <div class="mt-6 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <div class="mb-2 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                        {{ __('Agregar un día suelto') }}
                    </div>
                    <div class="flex items-end gap-2">
                        <flux:input wire:model="newDate" type="date" class="flex-1" />
                        <flux:button wire:click="addDate" size="sm" icon="plus" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Sesiones --}}
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-baseline justify-between gap-4 border-b border-zinc-100 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Calendario') }}</flux:heading>
                    <span class="text-sm tabular-nums text-zinc-500">
                        {{ trans_choice('{0}Sin días|{1}1 día|[2,*]:count días', $sessions->count(), ['count' => $sessions->count()]) }}
                    </span>
                </div>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($sessions as $session)
                        <li wire:key="session-{{ $session->id }}" class="flex items-center justify-between gap-4 px-5 py-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $session->date->translatedFormat('l j \d\e F \d\e Y') }}
                                </div>
                                <div class="text-xs text-zinc-500">
                                    @if ($session->attendances_count > 0)
                                        {{ trans_choice('{1}:count registro de asistencia|[2,*]:count registros de asistencia', $session->attendances_count, ['count' => $session->attendances_count]) }}
                                    @else
                                        {{ __('Sin asistencia registrada') }}
                                    @endif
                                </div>
                            </div>

                            <flux:button
                                wire:click="openDelete({{ $session->id }})"
                                size="sm"
                                icon="trash"
                                variant="danger"
                                inset="top bottom"
                            />
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-zinc-400">
                            {{ __('Todavía no hay días de clase. Configura el día de la semana y las fechas, y genera el calendario.') }}
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar este día de clase?') }}</flux:heading>

                @if ($deleting)
                    <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $deleting->date->translatedFormat('l j \d\e F \d\e Y') }}
                    </flux:text>

                    @if ($deleting->attendances_count > 0)
                        <flux:text class="mt-2">
                            {{ trans_choice(
                                '{1}Este día tiene :count registro de asistencia y se pierde con él.|[2,*]Este día tiene :count registros de asistencia y se pierden con él.',
                                $deleting->attendances_count,
                                ['count' => $deleting->attendances_count],
                            ) }}
                        </flux:text>
                    @else
                        <flux:text class="mt-2">{{ __('Todavía no tiene asistencia registrada.') }}</flux:text>
                    @endif
                @endif
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="removeSession" variant="danger" icon="trash">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
