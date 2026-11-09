<section class="w-full">
    <style>
        @media print {
            @page { margin: 1.4cm; }
            body * { visibility: hidden !important; }
            #certificate, #certificate * { visibility: visible !important; }
            #certificate {
                position: absolute; inset: 0; width: 100%;
                border: 0 !important; box-shadow: none !important;
                margin: 0 !important; border-radius: 0 !important;
            }
        }
    </style>

    {{-- Encabezado --}}
    <div class="mb-8 flex flex-wrap items-start justify-between gap-3 print:hidden">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="document-check" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Certificados') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Elige el semestre, el ciclo y el estudiante para ver, imprimir o descargar su certificado de calificaciones.') }}</flux:subheading>
        </div>

        <x-tour-button name="certificados" />
    </div>

    <x-term-cycle-steps :years="$years" :semesters="$semesters" :cycle-cards="$cycleCards" :cycle-id="$cycleId" />

    @if ($selected)
        @php $downloadParams = $combining ? ['both' => 1] : []; @endphp

        {{-- 3 · Estudiante --}}
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3 print:hidden">
            <div class="flex items-center gap-2">
                <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">3</span>
                <flux:heading size="lg">{{ __('Estudiante de :cycle', ['cycle' => $selected->name]) }}</flux:heading>
            </div>

            <div class="flex flex-wrap items-start gap-3" data-tour="cycle-options">
                @if ($hasPrevious)
                    <div class="max-w-sm">
                        @if ($canCombine)
                            <flux:checkbox wire:model.live="bothSemesters" :label="__('Incluir el semestre anterior (dos semestres)')" />
                        @else
                            <flux:checkbox
                                disabled
                                :label="__('Incluir el semestre anterior (dos semestres)')"
                                :description="__('Se habilita cuando los dos módulos de este semestre estén cerrados, porque certifica el ciclo completo.')"
                            />
                        @endif
                    </div>
                @endif

                <flux:button size="sm" icon="arrow-down-tray" :href="route('admin.certificates.download', ['cycle' => $selected->id] + $downloadParams)" target="_blank">
                    {{ __('Todo el ciclo (ZIP)') }}
                </flux:button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] print:block">
            <x-student-list :roster="$roster" :roster-total="$rosterTotal" :groups="$groups" :student-id="$studentId" :search="$search" :group-id="$groupId" class="lg:sticky lg:top-4 lg:self-start" />

            <div class="min-w-0" data-tour="preview">
                @if ($certificate)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $student->last_name }} {{ $student->first_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $combining ? __('Certificado de los dos semestres') : __('Certificado de :cycle', ['cycle' => $selected->label]) }}</div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" icon="document-arrow-down" :href="route('admin.certificates.download-student', ['cycle' => $selected->id, 'student' => $student->id] + $downloadParams)" target="_blank">
                                {{ __('Descargar PDF') }}
                            </flux:button>
                            <flux:button size="sm" variant="primary" icon="printer" x-on:click="window.print()">
                                {{ __('Imprimir') }}
                            </flux:button>
                        </div>
                    </div>

                    <x-certificate-card :certificate="$certificate" :issued-date="$issuedDate" />
                @else
                    <div class="flex h-full min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 px-6 py-16 text-center dark:border-zinc-700 print:hidden">
                        <flux:icon name="document-check" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text class="text-zinc-400">{{ __('Elige un estudiante de la lista para ver su certificado.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <x-tour name="certificados" :autostart="$startTour" :steps="[
        ['target' => null, 'title' => __('Así se sacan los certificados'), 'body' => __('En tres pasos llegas al certificado de cualquier estudiante. Puedes saltar esta guía y volver a verla con «¿Cómo funciona?».')],
        ['target' => 'term', 'title' => __('1. El semestre'), 'body' => __('Elige el año y el semestre. La pantalla abre en el más reciente.')],
        ['target' => 'cycles', 'title' => __('2. El ciclo'), 'body' => __('Cada tarjeta dice cómo está cada módulo. Con los dos cerrados, las notas ya son definitivas.')],
        ['target' => 'students', 'title' => __('3. El estudiante'), 'body' => __('Búscalo por nombre o documento. El número de la derecha dice cuántas notas tiene de las que le tocan: en verde, está completo.')],
        ['target' => 'cycle-options', 'title' => __('Dos semestres y todo el ciclo'), 'body' => __('El certificado de los dos semestres se habilita cuando los dos módulos de este semestre están cerrados. El ZIP trae el certificado de cada estudiante del ciclo en PDF.')],
        ['target' => 'help', 'title' => __('¿Dudas? Vuelve aquí'), 'body' => __('Este botón abre otra vez esta guía.')],
    ]" />
</section>
