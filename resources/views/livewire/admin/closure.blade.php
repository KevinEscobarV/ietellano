@php
    $long = 'j \d\e F, g:i a';
    $short = 'j \d\e F';
    $number = fn (int $n) => number_format($n, 0, ',', '.');
@endphp

<section class="w-full">
    {{-- Encabezado --}}
    <div class="mb-8 flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-3xl">
            <div class="mb-1.5 flex items-center gap-3">
                <flux:icon name="lock-closed" class="panel-icon" />
                <flux:heading size="xl" level="1">{{ __('Cierre de módulos') }}</flux:heading>
            </div>
            <flux:subheading>
                {{ __('Cada módulo se cierra por su lado: un módulo cerrado lo siguen viendo los docentes, pero ya no pueden cambiar sus notas ni su asistencia. Cuando un ciclo tiene los dos módulos cerrados, su semestre queda cerrado.') }}
            </flux:subheading>
        </div>

        <x-tour-button name="cierre" />
    </div>

    {{-- Semestre --}}
    <div class="mb-8 w-56" data-tour="term">
        <flux:select wire:model.live="term" :label="__('Semestre')">
            @foreach ($terms as $option)
                @php [$optionYear, $optionSemester] = explode('-', $option); @endphp
                <flux:select.option :value="$option">{{ __(':year · Semestre :n', ['year' => $optionYear, 'n' => $optionSemester]) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <flux:text class="text-zinc-400">{{ __('No hay ciclos en este semestre.') }}</flux:text>
        </div>
    @else
        {{-- Módulos del semestre --}}
        <div class="mb-8">
            <div class="mb-3">
                <flux:heading size="lg">{{ __('Módulos del semestre') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ trans_choice('{1}Lo que hagas aquí aplica al único ciclo del semestre.|[2,*]Lo que hagas aquí aplica a los :count ciclos del semestre a la vez.', $rows->count(), ['count' => $rows->count()]) }}
                </flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2" data-tour="modules">
                @foreach ($modules as $n => $module)
                    @php
                        $percent = $module['expected'] > 0 ? (int) round($module['graded'] / $module['expected'] * 100) : 0;
                        [$badgeColor, $badgeIcon, $badgeText] = match ($module['state']) {
                            'closed' => ['zinc', 'lock-closed', __('Cerrado')],
                            'scheduled' => ['amber', 'clock', __('Se cierra el :date', ['date' => $module['at']->translatedFormat($short)])],
                            'open' => ['emerald', 'lock-open', __('Abierto')],
                            default => ['sky', 'adjustments-horizontal', __('Cerrado en :closed de :total', ['closed' => $module['closed'], 'total' => $module['cycles']])],
                        };
                    @endphp

                    <div wire:key="module-{{ $n }}" @class([
                        'flex flex-col gap-4 rounded-xl border p-5',
                        'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/40' => $module['state'] === 'closed',
                        'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => $module['state'] !== 'closed',
                    ])>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="shrink-0">
                                <div class="whitespace-nowrap text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Módulo :n', ['n' => $n]) }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ trans_choice('{0}Sin materias todavía|{1}1 materia|[2,*]:count materias', $module['courses'], ['count' => $module['courses']]) }}
                                </div>
                            </div>

                            <flux:badge :color="$badgeColor" :icon="$badgeIcon">{{ $badgeText }}</flux:badge>
                        </div>

                        @if ($module['expected'] > 0)
                            <div>
                                <div class="mb-1 flex items-baseline justify-between text-xs text-zinc-500">
                                    <span class="tabular-nums">{{ __(':graded de :total notas puestas', ['graded' => $number($module['graded']), 'total' => $number($module['expected'])]) }}</span>
                                    <span class="tabular-nums font-medium text-zinc-700 dark:text-zinc-300">{{ $percent }}%</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200/70 dark:bg-zinc-700">
                                    <div
                                        @class([
                                            'h-full rounded-full',
                                            'bg-emerald-500' => $percent === 100,
                                            'bg-amber-500' => $percent < 100 && $percent > 0,
                                            'bg-zinc-300 dark:bg-zinc-600' => $percent === 0,
                                        ])
                                        style="width: {{ $percent }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif

                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            @switch($module['state'])
                                @case('closed')
                                    {{ __('Cerrado desde el :date. Los docentes solo pueden consultarlo.', ['date' => $module['at']->translatedFormat($short)]) }}
                                    @break
                                @case('scheduled')
                                    {{ __('Se cierra solo el :date; hasta entonces los docentes pueden editar.', ['date' => $module['at']->translatedFormat($long)]) }}
                                    @break
                                @case('open')
                                    {{ __('Abierto para los docentes, sin fecha de cierre.') }}
                                    @break
                                @default
                                    {{ __('Los ciclos van distinto. Abajo está el detalle de cada uno.') }}
                            @endswitch
                        </p>

                        <div class="mt-auto flex flex-wrap gap-2">
                            @if ($module['state'] !== 'closed')
                                <flux:button wire:click="confirmModule('close', {{ $n }})" size="sm" variant="primary" icon="lock-closed">
                                    {{ $module['state'] === 'mixed' ? __('Cerrar en todos') : __('Cerrar ahora') }}
                                </flux:button>
                                <flux:button wire:click="openScheduleForm({{ $n }})" size="sm" icon="calendar">
                                    {{ $module['state'] === 'scheduled' ? __('Cambiar fecha') : __('Programar cierre') }}
                                </flux:button>
                            @endif
                            @if ($module['state'] === 'closed' || $module['state'] === 'mixed')
                                <flux:button wire:click="confirmModule('reopen', {{ $n }})" size="sm" variant="ghost" icon="lock-open">
                                    {{ $module['state'] === 'mixed' ? __('Reabrir en todos') : __('Reabrir') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Por ciclo --}}
            <div class="lg:col-span-3" data-tour="cycles">
                <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-100 p-5 dark:border-zinc-800">
                        <flux:heading size="lg">{{ __('Por ciclo') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('Para cuando un ciclo va a otro ritmo. Haz clic en un módulo para cerrarlo, programarlo o reabrirlo solo en ese ciclo.') }}</flux:text>
                    </div>

                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rows as $row)
                            @php $cycle = $row['cycle']; @endphp

                            <li wire:key="cycle-{{ $cycle->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $cycle->name }}</span>
                                        @if ($row['closed'])
                                            <flux:badge size="sm" color="rose" icon="lock-closed">{{ __('Semestre cerrado') }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-zinc-500">
                                        @foreach ($row['modules'] as $n => $module)
                                            @if ($module['courses'] > 0)
                                                <span>{{ trans_choice('{0}M:n completo|{1}M:n: falta 1 nota|[2,*]M:n: faltan :count notas', $module['pending'], ['n' => $n, 'count' => $number($module['pending'])]) }}</span>
                                            @else
                                                <span>{{ __('M:n sin materias', ['n' => $n]) }}</span>
                                            @endif
                                            @unless ($loop->last) <span class="text-zinc-300 dark:text-zinc-600">·</span> @endunless
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    @foreach ($row['modules'] as $n => $module)
                                        <flux:dropdown wire:key="cycle-{{ $cycle->id }}-module-{{ $n }}" position="bottom" align="end">
                                            <flux:button size="sm" variant="ghost" icon-trailing="chevron-down" :aria-label="__('Opciones del Módulo :n de :cycle', ['n' => $n, 'cycle' => $cycle->name])">
                                                <x-module-status :module="$n" :state="$module['state']" :at="$module['at']" inset="top bottom" />
                                            </flux:button>

                                            <flux:menu>
                                                <flux:menu.heading>{{ __('Módulo :n · :cycle', ['n' => $n, 'cycle' => $cycle->name]) }}</flux:menu.heading>

                                                @if ($module['state'] === 'closed')
                                                    <flux:menu.item icon="lock-open" wire:click="confirmModule('reopen', {{ $n }}, {{ $cycle->id }})">{{ __('Reabrir') }}</flux:menu.item>
                                                    <flux:menu.item icon="clock" wire:click="openWindowForm({{ $cycle->id }}, {{ $n }})">{{ __('Abrir una ventana') }}</flux:menu.item>
                                                @else
                                                    <flux:menu.item icon="lock-closed" wire:click="confirmModule('close', {{ $n }}, {{ $cycle->id }})">{{ __('Cerrar ahora') }}</flux:menu.item>
                                                    <flux:menu.item icon="calendar" wire:click="openScheduleForm({{ $n }}, {{ $cycle->id }})">
                                                        {{ $module['state'] === 'scheduled' ? __('Cambiar la fecha de cierre') : __('Programar cierre') }}
                                                    </flux:menu.item>
                                                    @if ($module['state'] === 'scheduled')
                                                        <flux:menu.item icon="x-mark" wire:click="confirmModule('reopen', {{ $n }}, {{ $cycle->id }})">{{ __('Quitar la fecha de cierre') }}</flux:menu.item>
                                                    @endif
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Ventanas --}}
            <div class="lg:col-span-2" data-tour="windows">
                <div class="rounded-xl border border-zinc-200 bg-white shadow-card dark:shadow-none dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3 border-b border-zinc-100 p-5 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg">{{ __('Ventanas de edición') }}</flux:heading>
                            <flux:text class="text-sm">{{ __('Un permiso con plazo para corregir algo de un módulo ya cerrado.') }}</flux:text>
                        </div>
                        <flux:button wire:click="openWindowForm" size="sm" variant="primary" icon="plus" :aria-label="__('Abrir una ventana')" />
                    </div>

                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($active as $window)
                            <x-edit-window-item :window="$window" wire:key="window-{{ $window->id }}" />
                        @empty
                            <li class="p-8 text-center text-sm text-zinc-400">
                                {{ __('No hay ventanas abiertas en este semestre.') }}
                            </li>
                        @endforelse
                    </ul>

                    {{-- Las vencidas son constancia, no algo por hacer: quedan plegadas. --}}
                    @if ($expiredCount > 0)
                        <details class="group border-t border-zinc-100 dark:border-zinc-800">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-5 py-3 text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                <span>{{ trans_choice('{1}1 ventana vencida|[2,*]:count ventanas vencidas', $expiredCount, ['count' => $expiredCount]) }}</span>
                                <flux:icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                            </summary>

                            <ul class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                                @foreach ($expired as $window)
                                    <x-edit-window-item :window="$window" wire:key="expired-{{ $window->id }}" />
                                @endforeach
                            </ul>

                            @if ($expiredCount > $expired->count())
                                <p class="px-5 py-3 text-xs text-zinc-400">{{ __('Se muestran las :count más recientes.', ['count' => $expired->count()]) }}</p>
                            @endif
                        </details>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Confirmación --}}
    <flux:modal wire:model="showConfirmModal" class="w-full max-w-md">
        @php
            $aviso = match ($confirming) {
                'close' => [
                    'title' => __('¿Cerrar el Módulo :n?', ['n' => $confirmingPeriod]),
                    'body' => __('Los docentes lo seguirán viendo, pero ya no podrán cambiar sus notas ni su asistencia, y las ventanas abiertas para este módulo se cierran. Tú puedes seguir corrigiendo desde Editar notas. Si el otro módulo ya estaba cerrado, el semestre queda cerrado.'),
                    'action' => __('Cerrar módulo'),
                    'icon' => 'lock-closed',
                    'variant' => 'primary',
                ],
                'reopen' => [
                    'title' => __('¿Reabrir el Módulo :n?', ['n' => $confirmingPeriod]),
                    'body' => __('Vuelve a quedar editable para sus docentes, sin fecha de cierre. Si solo hace falta una corrección puntual, es mejor abrir una ventana.'),
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
                @if ($confirm['target'] !== '')
                    <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-200">{{ $confirm['target'] }}</flux:text>
                @endif
                <flux:text class="mt-2">{{ $aviso['body'] }}</flux:text>

                @if ($confirming === 'close' && $confirm['pending'] > 0)
                    <div class="mt-4 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                        <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                        <span>{{ trans_choice('{1}Todavía falta 1 nota por poner.|[2,*]Todavía faltan :count notas por poner.', $confirm['pending'], ['count' => $number($confirm['pending'])]) }}</span>
                    </div>
                @endif
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

    {{-- Programar cierre --}}
    <flux:modal wire:model="showScheduleModal" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Programar el cierre del Módulo :n', ['n' => $schedulePeriod]) }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Hasta esa hora los docentes pueden poner y corregir notas; después el módulo se cierra solo. Ellos ven la fecha en su planilla.') }}
                </flux:text>
            </div>

            <flux:input wire:model="scheduleAt" type="datetime-local" :label="__('Se cierra el')" />

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showScheduleModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="scheduleClosure" variant="primary" icon="calendar">{{ __('Programar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Nueva ventana --}}
    <flux:modal wire:model="showWindowModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Abrir una ventana de edición') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Mientras dure, el docente vuelve a poder cambiar notas y asistencia de un módulo cerrado. Se cierra sola al llegar la fecha.') }}
                </flux:text>
            </div>

            <div class="space-y-4">
                <flux:select wire:model.live="cycleId" :label="__('Ciclo')">
                    @foreach ($windowCycles as $option)
                        <flux:select.option :value="$option->id">{{ $option->label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="windowPeriod" :label="__('Módulo')">
                    <flux:select.option value="">{{ __('Los dos módulos') }}</flux:select.option>
                    @foreach (\App\Models\Cycle::MODULES as $option)
                        <flux:select.option :value="(string) $option">{{ __('Módulo :n', ['n' => $option]) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="courseId" :label="__('Materia')">
                    <flux:select.option value="">{{ $windowPeriod === '' ? __('Todas — se reabre el ciclo entero') : __('Todas las del módulo') }}</flux:select.option>
                    @foreach ($windowCourses as $course)
                        <flux:select.option :value="$course->id">{{ $course->subject?->name ?? $course->code }}@if ($course->group) · {{ $course->group->name }}@endif · {{ __('Módulo :n', ['n' => $course->period]) }}@if ($course->teacher) — {{ $course->teacher->name }}@endif</flux:select.option>
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

    <x-tour name="cierre" :autostart="$startTour" :steps="[
        ['target' => null, 'title' => __('Así se cierran las notas'), 'body' => __('Se cierra por módulo: puedes dejar el Módulo 1 en solo lectura mientras el Módulo 2 todavía se califica. Puedes saltar esta guía y volver a verla con «¿Cómo funciona?».')],
        ['target' => 'term', 'title' => __('El semestre'), 'body' => __('Elige qué semestre vas a cerrar. La pantalla abre en el más reciente.')],
        ['target' => 'modules', 'title' => __('Un módulo, todos los ciclos'), 'body' => __('«Cerrar ahora» cierra ese módulo en todos los ciclos del semestre. «Programar cierre» lo deja abierto hasta una fecha y hora, y se cierra solo. La barra dice cuántas notas van puestas.')],
        ['target' => 'cycles', 'title' => __('Ciclo por ciclo'), 'body' => __('Si un ciclo va a otro ritmo, haz clic en su M1 o M2 para cerrarlo, programarlo o reabrirlo solo ahí. Con los dos módulos cerrados, el semestre del ciclo queda cerrado y se habilita el certificado de los dos semestres.')],
        ['target' => 'windows', 'title' => __('Correcciones después del cierre'), 'body' => __('Una ventana le devuelve al docente el permiso por unos días, para una materia o un módulo entero, sin reabrir nada. Tú siempre puedes corregir desde Editar notas.')],
        ['target' => 'help', 'title' => __('¿Dudas? Vuelve aquí'), 'body' => __('Este botón abre otra vez esta guía.')],
    ]" />
</section>
