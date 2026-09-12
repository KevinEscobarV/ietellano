<div class="flex flex-col gap-6">
    @if ($student)
        {{-- Quién está consultando --}}
        <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ __('Hola, :name', ['name' => \Illuminate\Support\Str::title($student->first_name)]) }}
                </h1>
                <p class="mt-0.5 text-sm text-zinc-500">
                    {{ __('Documento') }} {{ $student->document }}
                </p>
            </div>

            <flux:button variant="subtle" icon="arrow-right-start-on-rectangle" wire:click="salir">
                {{ __('Salir') }}
            </flux:button>
        </div>

        @if ($cycles->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
                <flux:icon name="academic-cap" class="mx-auto mb-3 size-10 text-zinc-300" />
                <flux:text class="text-zinc-500">
                    {{ __('Todavía no apareces matriculado en ningún semestre. Acércate a secretaría para revisar tu matrícula.') }}
                </flux:text>
            </div>
        @else
            {{-- Los semestres del estudiante. Con uno solo no hay nada que elegir. --}}
            @if ($cycles->count() > 1)
                <div class="flex flex-wrap gap-2 print:hidden">
                    @foreach ($cycles as $option)
                        <button
                            type="button"
                            wire:click="selectCycle({{ $option->id }})"
                            @class([
                                'rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                                'border-emerald-600 bg-emerald-600 text-white' => $option->id === $cycleId,
                                'border-zinc-300 bg-white text-zinc-600 hover:border-zinc-400 hover:text-zinc-900' => $option->id !== $cycleId,
                            ])
                        >
                            {{ $option->label }}
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($cycle && ! $cycle->isClosed())
                <div class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 print:hidden">
                    <flux:icon name="clock" class="mt-0.5 size-5 shrink-0 text-amber-600" />
                    <div>
                        <div class="text-sm font-medium text-amber-900">{{ __('Semestre en curso') }}</div>
                        <p class="mt-0.5 text-sm text-amber-800">
                            {{ __('Este boletín es parcial: los docentes todavía pueden registrar o corregir notas y asistencias hasta que se cierre el semestre.') }}
                        </p>
                    </div>
                </div>
            @endif

            @if ($hasPrevious)
                <div class="print:hidden">
                    <flux:checkbox wire:model.live="bothSemesters" :label="__('Incluir el semestre anterior (boletín de los dos semestres)')" />
                </div>
            @endif

            @if ($boletin)
                @unless ($hasGrades)
                    <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 print:hidden">
                        <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                        <div>
                            <div class="text-sm font-medium text-zinc-800">{{ __('Aún no hay notas registradas') }}</div>
                            <p class="mt-0.5 text-sm text-zinc-500">
                                {{ __('Tus docentes todavía no han cargado calificaciones de este semestre. Vuelve a consultar más adelante.') }}
                            </p>
                        </div>
                    </div>
                @endunless

                @php $downloadParams = $bothSemesters && $hasPrevious ? ['both' => 1] : []; @endphp

                <div class="flex flex-wrap items-center gap-3 print:hidden">
                    <flux:button
                        variant="primary"
                        icon="document-arrow-down"
                        :href="route('consulta.boletin.download', ['cycle' => $cycle->id] + $downloadParams)"
                        target="_blank"
                    >
                        {{ __('Descargar PDF') }}
                    </flux:button>

                    <flux:button variant="filled" icon="printer" x-on:click="window.print()">
                        {{ __('Imprimir') }}
                    </flux:button>
                </div>

                <x-boletin-card :boletin="$boletin" :passing-score="$passingScore" />
            @endif

            <p class="text-xs leading-relaxed text-zinc-500 print:hidden">
                {{ __('Este boletín es un documento informativo para el estudiante. Los certificados de estudio se solicitan en la institución.') }}
            </p>
        @endif
    @endif
</div>
