@props(['editable' => true, 'until' => null])

{{-- El aviso de que el semestre ya cerró, o de hasta cuándo dura el permiso
     para corregirlo. Lo comparten la planilla de notas y la de asistencia. --}}

@if (! $editable)
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
        <flux:icon name="lock-closed" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
        <div>
            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Este semestre está cerrado') }}</div>
            <p class="mt-0.5 text-sm text-zinc-500">
                {{ __('Puedes consultarlo, pero ya no se puede modificar. Si necesitas corregir algo, pídele a coordinación que te abra una ventana de edición.') }}
            </p>
        </div>
    </div>
@elseif ($until !== null)
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
        <flux:icon name="clock" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
        <div>
            <div class="text-sm font-medium text-amber-900 dark:text-amber-200">{{ __('Tienes una ventana de edición abierta') }}</div>
            <p class="mt-0.5 text-sm text-amber-800 dark:text-amber-300">
                {{ __('El semestre está cerrado. Lo que cambies aquí se guarda hasta el :date; después vuelve a quedar en solo lectura.', ['date' => $until->translatedFormat('j \d\e F \d\e Y, g:i a')]) }}
            </p>
        </div>
    </div>
@endif
