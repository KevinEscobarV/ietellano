<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8">
        <div class="mb-1 flex items-center gap-2">
            <flux:icon name="arrow-up-tray" class="size-6 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="xl" level="1">{{ __('Calificaciones') }}</flux:heading>
        </div>
        <flux:subheading>{{ __('Sube los archivos .xlsx de notas. El nombre de cada archivo debe ser el código del curso (ej. C5MATM1.xlsx).') }}</flux:subheading>
    </div>

    <form wire:submit="import" class="space-y-6">
        <div>
            <flux:input
                type="file"
                wire:model="files"
                multiple
                accept=".xlsx"
                :label="__('Archivos de calificaciones')"
                :description="__('Puedes seleccionar varios archivos a la vez. Cada archivo es una materia en un periodo.')"
            />
            @error('files.*')
                <flux:text class="mt-1 text-red-500">{{ $message }}</flux:text>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="import">{{ __('Importar notas') }}</span>
                <span wire:loading wire:target="import">{{ __('Procesando...') }}</span>
            </flux:button>
            <flux:text wire:loading wire:target="files" class="text-zinc-500">{{ __('Cargando archivos...') }}</flux:text>
        </div>
    </form>

    @if ($results)
        <div class="mt-8">
            <flux:heading size="lg" class="mb-3">{{ __('Resultado') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Archivo') }}</flux:table.column>
                    <flux:table.column>{{ __('Curso') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Notas') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Sin cruzar') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Sin nota') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($results as $result)
                        <flux:table.row>
                            <flux:table.cell>{{ $result['file'] }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($result['course'])
                                    <flux:badge size="sm" color="emerald" inset="top bottom">{{ $result['course'] }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red" inset="top bottom">{{ __('Sin curso') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="center">{{ $result['course'] ? $result['matched'] : '—' }}</flux:table.cell>
                            <flux:table.cell align="center">
                                @if ($result['course'] && $result['unmatched'] > 0)
                                    <span class="text-amber-600 dark:text-amber-400">{{ $result['unmatched'] }}</span>
                                @else
                                    {{ $result['course'] ? $result['unmatched'] : '—' }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="center">{{ $result['course'] ? $result['blank'] : '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @php($unmatched = collect($results)->flatMap(fn ($r) => collect($r['unmatched_rows'] ?? [])->map(fn ($u) => $u + ['course' => $r['course']])))
            @if ($unmatched->isNotEmpty())
                <div class="mt-6">
                    <flux:heading size="sm" class="mb-2 text-amber-600 dark:text-amber-400">
                        {{ __('Filas sin cruzar (:count)', ['count' => $unmatched->count()]) }}
                    </flux:heading>
                    <flux:text size="sm" class="mb-3 text-zinc-500">{{ __('Estas notas no se guardaron porque el estudiante no está matriculado o su email/documento no coincide.') }}</flux:text>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Curso') }}</flux:table.column>
                            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                            <flux:table.column>{{ __('Email') }}</flux:table.column>
                            <flux:table.column>{{ __('Documento') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($unmatched as $row)
                                <flux:table.row>
                                    <flux:table.cell>{{ $row['course'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['email'] ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['document'] ?? '—' }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    @endif
</section>
