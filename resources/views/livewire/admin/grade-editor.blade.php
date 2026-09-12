<section class="w-full">
    <div class="mb-8">
        <div class="mb-1 flex items-center gap-2">
            <flux:icon name="pencil-square" class="size-6 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="xl" level="1">{{ __('Editar notas') }}</flux:heading>
        </div>
        <flux:subheading>{{ __('Editar las calificaciones de un curso (materia + periodo)') }}</flux:subheading>
    </div>

    {{-- Filters --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <flux:select wire:model.live="cycleId" :label="__('Ciclo')" :placeholder="__('Selecciona un ciclo')">
            @foreach ($cycles as $cycle)
                <flux:select.option :value="$cycle->id">{{ $cycle->label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="courseId" :label="__('Curso')" :placeholder="__('Selecciona un curso')" :disabled="! $cycleId">
            @foreach ($courses as $c)
                <flux:select.option :value="$c->id">
                    {{ $c->subject->name }} · P{{ $c->period }}@if ($c->group) · {{ $c->group->name }}@endif
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($course)
        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800/50">
            <span><span class="text-zinc-500">{{ __('Curso') }}:</span> <span class="font-medium">{{ $course->code }}</span></span>
            <span><span class="text-zinc-500">{{ __('Materia') }}:</span> <span class="font-medium">{{ $course->subject->name }}</span></span>
            <span><span class="text-zinc-500">{{ __('Periodo') }}:</span> <span class="font-medium">{{ $course->period }}</span></span>
            <span><span class="text-zinc-500">{{ __('Docente') }}:</span> <span class="font-medium">{{ $course->teacher->name ?? '—' }}</span></span>
        </div>

        <form wire:submit="save">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Estudiante') }}</flux:table.column>
                    <flux:table.column>{{ __('Documento') }}</flux:table.column>
                    <flux:table.column class="w-40">{{ __('Nota (0–5)') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($students as $student)
                        <flux:table.row :key="$student->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $student->last_name }} {{ $student->first_name }}</div>
                                <div class="text-sm text-zinc-500">{{ $student->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $student->document ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="5"
                                    size="sm"
                                    wire:model="grades.{{ $student->id }}"
                                    :placeholder="__('Sin nota')"
                                />
                                @error("grades.{$student->id}")
                                    <flux:text size="sm" class="mt-1 text-red-500">{{ $message }}</flux:text>
                                @enderror
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">
                                <div class="py-8 text-center text-zinc-400">{{ __('Este curso no tiene estudiantes matriculados.') }}</div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            @if ($students->isNotEmpty())
                <div class="mt-4 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        {{ __('Guardar notas') }}
                    </flux:button>
                </div>
            @endif
        </form>
    @elseif ($cycleId)
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <flux:icon name="pencil-square" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">{{ __('Selecciona un curso para editar sus notas.') }}</flux:text>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <flux:icon name="pencil-square" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">{{ __('Selecciona un ciclo y un curso para empezar.') }}</flux:text>
        </div>
    @endif
</section>
