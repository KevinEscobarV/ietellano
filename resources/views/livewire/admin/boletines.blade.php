<section class="w-full">
    {{-- Encabezado --}}
    <div class="mb-8 flex flex-wrap items-start justify-between gap-3 print:hidden">
        <div>
            <div class="mb-1.5 flex items-center gap-3">
                <flux:icon name="document-text" class="panel-icon" />
                <flux:heading size="xl" level="1">{{ __('Boletines') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Elige el semestre, el ciclo y el estudiante para ver, imprimir o descargar su boletín.') }}</flux:subheading>
        </div>

        <x-tour-button name="boletines" />
    </div>

    <x-term-cycle-steps :years="$years" :semesters="$semesters" :cycle-cards="$cycleCards" :cycle-id="$cycleId" />

    @if ($selected)
        @php $downloadParams = $bothSemesters && $hasPrevious ? ['both' => 1] : []; @endphp

        {{-- 3 · Estudiante --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div class="flex items-center gap-2">
                <span class="flex size-6 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground">3</span>
                <flux:heading size="lg">{{ __('Estudiante de :cycle', ['cycle' => $selected->name]) }}</flux:heading>
            </div>

            <div class="flex flex-wrap items-center gap-3" data-tour="cycle-options">
                @if ($hasPrevious)
                    <flux:checkbox wire:model.live="bothSemesters" :label="__('Incluir el semestre anterior')" />
                @endif

                <flux:button size="sm" icon="arrow-down-tray" :href="route('admin.boletines.download', ['cycle' => $selected->id] + $downloadParams)" target="_blank">
                    {{ __('Todo el ciclo (ZIP)') }}
                </flux:button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] print:block">
            <x-student-list :roster="$roster" :roster-total="$rosterTotal" :groups="$groups" :student-id="$studentId" :search="$search" :group-id="$groupId" class="lg:sticky lg:top-4 lg:self-start" />

            <div class="min-w-0" data-tour="preview">
                @if ($boletin)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $student->last_name }} {{ $student->first_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $bothSemesters && $hasPrevious ? __('Boletín de los dos semestres') : __('Boletín de :cycle', ['cycle' => $selected->label]) }}</div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" icon="document-arrow-down" :href="route('admin.boletines.download-student', ['cycle' => $selected->id, 'student' => $student->id] + $downloadParams)" target="_blank">
                                {{ __('Descargar PDF') }}
                            </flux:button>
                            <flux:button size="sm" variant="primary" icon="printer" x-on:click="window.print()">
                                {{ __('Imprimir') }}
                            </flux:button>
                        </div>
                    </div>

                    @unless ($selected->isClosed())
                        <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200 print:hidden">
                            <flux:icon name="clock" class="mt-0.5 size-4 shrink-0" />
                            <span>{{ __('El semestre sigue en curso: este boletín es parcial mientras haya un módulo abierto.') }}</span>
                        </div>
                    @endunless

                    <x-boletin-card :boletin="$boletin" :passing-score="$passingScore" />
                @else
                    <div class="flex h-full min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 px-6 py-16 text-center dark:border-zinc-700 print:hidden">
                        <flux:icon name="document-text" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text class="text-zinc-400">{{ __('Elige un estudiante de la lista para ver su boletín.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <x-tour name="boletines" :autostart="$startTour" :steps="[
        ['target' => null, 'title' => __('Así se sacan los boletines'), 'body' => __('En tres pasos llegas al boletín de cualquier estudiante. Puedes saltar esta guía y volver a verla con «¿Cómo funciona?».')],
        ['target' => 'term', 'title' => __('1. El semestre'), 'body' => __('Elige el año y el semestre. La pantalla abre en el más reciente.')],
        ['target' => 'cycles', 'title' => __('2. El ciclo'), 'body' => __('Cada tarjeta dice cómo está cada módulo. Mientras haya uno abierto, el boletín es parcial: los docentes todavía pueden cambiar notas.')],
        ['target' => 'students', 'title' => __('3. El estudiante'), 'body' => __('Búscalo por nombre o documento. El número de la derecha dice cuántas notas tiene de las que le tocan: en verde, está completo.')],
        ['target' => 'cycle-options', 'title' => __('Todo el ciclo de una vez'), 'body' => __('El ZIP trae el boletín de cada estudiante del ciclo en PDF. Si el ciclo viene de un semestre anterior, puedes incluirlo.')],
        ['target' => 'help', 'title' => __('¿Dudas? Vuelve aquí'), 'body' => __('Este botón abre otra vez esta guía.')],
    ]" />
</section>
