<section class="w-full">
    <div class="mb-6">
        <div class="mb-1 flex items-center gap-2">
            <flux:icon name="lock-closed" class="size-6 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="xl" level="1">{{ __('Cierre de semestre') }}</flux:heading>
        </div>
        <flux:subheading>
            {{ __('Un semestre cerrado lo siguen viendo los docentes, pero ya no pueden cambiar notas ni asistencia. Para una corrección puntual, ábreles una ventana con plazo.') }}
        </flux:subheading>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Semestres --}}
        <div class="lg:col-span-3">
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-100 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Semestres') }}</flux:heading>
                </div>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($cycles as $cycle)
                        <li wire:key="cycle-{{ $cycle->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $cycle->label }}</span>
                                    @if ($cycle->isClosed())
                                        <flux:badge size="sm" color="rose">{{ __('Cerrado') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="emerald">{{ __('Abierto') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-xs text-zinc-500">
                                    {{ trans_choice('{0}Sin materias|{1}1 materia|[2,*]:count materias', $cycle->courses_count, ['count' => $cycle->courses_count]) }}
                                    @if ($cycle->isClosed())
                                        · {{ __('cerrado el :date', ['date' => $cycle->closed_at->translatedFormat('j \d\e F \d\e Y')]) }}
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                @if ($cycle->isClosed())
                                    <flux:button wire:click="openWindowForm({{ $cycle->id }})" size="sm" icon="lock-open" variant="ghost">
                                        {{ __('Abrir ventana') }}
                                    </flux:button>

                                    <flux:button wire:click="confirm('reopen', {{ $cycle->id }})" size="sm" variant="ghost">
                                        {{ __('Reabrir') }}
                                    </flux:button>
                                @else
                                    <flux:button wire:click="confirm('close', {{ $cycle->id }})" size="sm" icon="lock-closed">
                                        {{ __('Cerrar') }}
                                    </flux:button>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-zinc-400">{{ __('No hay ciclos.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Ventanas --}}
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">{{ __('Ventanas de edición') }}</flux:heading>
                    <flux:button wire:click="openWindowForm" size="sm" variant="primary" icon="plus" />
                </div>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($windows as $window)
                        @php $active = $window->isActive(); @endphp

                        <li wire:key="window-{{ $window->id }}" @class(['px-5 py-4', 'opacity-60' => ! $active])>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                        @if ($window->course)
                                            {{ $window->course->subject?->name ?? $window->course->code }}
                                            @if ($window->course->group)
                                                <span class="font-normal text-zinc-500">· {{ $window->course->group->name }}</span>
                                            @endif
                                        @else
                                            {{ __('Todo el semestre') }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500">{{ $window->cycle?->label }}</div>
                                </div>

                                @if ($active)
                                    <flux:button wire:click="confirm('window', {{ $window->id }})" size="xs" variant="ghost">
                                        {{ __('Cerrar') }}
                                    </flux:button>
                                @endif
                            </div>

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                <span @class([
                                    'rounded-full px-2 py-0.5 font-medium',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $active,
                                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $active,
                                ])>
                                    @if ($active)
                                        {{ __('hasta el :date', ['date' => $window->closes_at->translatedFormat('j \d\e F, g:i a')]) }}
                                    @else
                                        {{ __('venció el :date', ['date' => $window->closes_at->translatedFormat('j \d\e F, g:i a')]) }}
                                    @endif
                                </span>

                                @if ($window->openedBy)
                                    <span class="text-zinc-400">{{ __('la abrió :name', ['name' => $window->openedBy->name]) }}</span>
                                @endif
                            </div>

                            @if ($window->note)
                                <p class="mt-1.5 text-xs text-zinc-500">{{ $window->note }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-zinc-400">
                            {{ __('Todavía no se ha abierto ninguna ventana.') }}
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Confirmación --}}
    <flux:modal wire:model="showConfirmModal" class="w-full max-w-md">
        @php
            $aviso = match ($confirming) {
                'close' => [
                    'title' => __('¿Cerrar el semestre?'),
                    'body' => __('Sus docentes dejarán de poder cambiar notas y asistencia. Tú puedes seguir editando desde el panel, y abrirles una ventana cuando necesiten corregir algo.'),
                    'action' => __('Cerrar semestre'),
                    'icon' => 'lock-closed',
                    'variant' => 'primary',
                ],
                'reopen' => [
                    'title' => __('¿Reabrir el semestre?'),
                    'body' => __('Vuelve a quedar editable para todos sus docentes, sin plazo. Si solo hace falta una corrección puntual, es mejor abrir una ventana.'),
                    'action' => __('Reabrir'),
                    'icon' => 'lock-open',
                    'variant' => 'primary',
                ],
                default => [
                    'title' => __('¿Cerrar la ventana?'),
                    'body' => __('El docente dejará de poder editar ahora mismo, antes de la fecha que tenía. La ventana queda registrada.'),
                    'action' => __('Cerrar ventana'),
                    'icon' => 'lock-closed',
                    'variant' => 'danger',
                ],
            };
        @endphp

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $aviso['title'] }}</flux:heading>
                @if ($confirmTarget !== '')
                    <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-200">{{ $confirmTarget }}</flux:text>
                @endif
                <flux:text class="mt-2">{{ $aviso['body'] }}</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="confirmed" :variant="$aviso['variant']" :icon="$aviso['icon']">
                    {{ $aviso['action'] }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Nueva ventana --}}
    <flux:modal wire:model="showWindowModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Abrir una ventana de edición') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Mientras dure, el docente vuelve a poder cambiar notas y asistencia. Se cierra sola al llegar la fecha.') }}
                </flux:text>
            </div>

            <div class="space-y-4">
                <flux:select wire:model.live="cycleId" :label="__('Semestre')">
                    @foreach ($cycles as $option)
                        <flux:select.option :value="$option->id">{{ $option->label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="courseId" :label="__('Materia')">
                    <flux:select.option value="">{{ __('Todas — se reabre el semestre entero') }}</flux:select.option>
                    @foreach ($courses as $course)
                        <flux:select.option :value="$course->id">{{ $course->subject?->name ?? $course->code }}@if ($course->group) · {{ $course->group->name }}@endif · {{ __('Periodo :n', ['n' => $course->period]) }}@if ($course->teacher) — {{ $course->teacher->name }}@endif</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="closesAt" type="datetime-local" :label="__('Se cierra el')" />

                <flux:input
                    wire:model="note"
                    :label="__('Motivo')"
                    :placeholder="__('Corrección de la nota de un estudiante')"
                    :description="__('Opcional. Queda guardado junto con quién autorizó la ventana.')"
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showWindowModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="openWindow" variant="primary" icon="lock-open">{{ __('Abrir') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
