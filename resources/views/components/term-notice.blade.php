@props(['editable' => true, 'until' => null, 'closed' => false, 'module' => null])

{{-- Si el módulo de la materia ya cerró, hasta cuándo dura el permiso para
     corregirlo o cuándo se va a cerrar. Lo comparten la planilla de notas y
     la de asistencia. --}}

@php
    $name = $module !== null ? __('El Módulo :n', ['n' => $module]) : __('Este módulo');
@endphp

@if (! $editable)
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
        <flux:icon name="lock-closed" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
        <div>
            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __(':module está cerrado', ['module' => $name]) }}</div>
            <p class="mt-0.5 text-sm text-zinc-500">
                {{ __('Puedes consultarlo, pero ya no se puede modificar. Si necesitas corregir algo, pídele a coordinación que te abra una ventana de edición.') }}
            </p>
        </div>
    </div>
@elseif ($until !== null && $closed)
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
        <flux:icon name="clock" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
        <div>
            <div class="text-sm font-medium text-amber-900 dark:text-amber-200">{{ __('Tienes una ventana de edición abierta') }}</div>
            <p class="mt-0.5 text-sm text-amber-800 dark:text-amber-300">
                {{ __(':module está cerrado. Lo que cambies aquí se guarda hasta el :date; después vuelve a quedar en solo lectura.', ['module' => $name, 'date' => $until->translatedFormat('j \d\e F \d\e Y, g:i a')]) }}
            </p>
        </div>
    </div>
@elseif ($until !== null)
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-500/30 dark:bg-sky-500/10">
        <flux:icon name="calendar" class="mt-0.5 size-5 shrink-0 text-sky-600 dark:text-sky-400" />
        <div>
            <div class="text-sm font-medium text-sky-900 dark:text-sky-200">{{ __(':module se cierra el :date', ['module' => $name, 'date' => $until->translatedFormat('j \d\e F, g:i a')]) }}</div>
            <p class="mt-0.5 text-sm text-sky-800 dark:text-sky-300">
                {{ __('Hasta esa hora puedes poner y corregir notas y asistencia. Después solo se podrá consultar.') }}
            </p>
        </div>
    </div>
@endif
