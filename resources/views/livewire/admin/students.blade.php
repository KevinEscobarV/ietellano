<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="academic-cap" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Estudiantes') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Administrar estudiantes y sus matrículas') }}</flux:subheading>
        </div>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            {{ __('Nuevo estudiante') }}
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <flux:input
            wire:model.live.debounce="search"
            icon="magnifying-glass"
            :placeholder="__('Buscar por nombre, email o documento...')"
            class="max-w-sm"
        />
    </div>

    {{-- Table --}}
    <flux:table :paginate="$students">
        <flux:table.columns>
            <flux:table.column>{{ __('Estudiante') }}</flux:table.column>
            <flux:table.column>{{ __('Documento') }}</flux:table.column>
            <flux:table.column>{{ __('Matrículas') }}</flux:table.column>
            <flux:table.column></flux:table.column>
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
                        @if ($student->enrollments->isEmpty())
                            <flux:text size="sm" class="italic text-zinc-400">{{ __('Sin matrícula') }}</flux:text>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach ($student->enrollments as $enrollment)
                                    <flux:badge size="sm" variant="outline" color="zinc" inset="top bottom">
                                        {{ $enrollment->cycle->name }}@if ($enrollment->group) · {{ $enrollment->group->name }}@endif
                                    </flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $student->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" :tooltip="__('Editar')" />
                            <flux:button wire:click="openDelete({{ $student->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" :tooltip="__('Eliminar')" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <div class="py-12 text-center">
                            <flux:icon name="academic-cap" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:heading size="sm" class="mb-1 text-zinc-500">{{ __('No hay estudiantes') }}</flux:heading>
                            <flux:text size="sm" class="text-zinc-400">
                                {{ $search ? __('Ningún estudiante coincide con la búsqueda.') : __('Crea el primer estudiante.') }}
                            </flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Delete Modal --}}
    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar estudiante?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Se eliminará el estudiante y sus matrículas y notas. Esta acción no se puede deshacer.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Create / Edit Modal --}}
    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Editar estudiante') : __('Nuevo estudiante') }}</flux:heading>
                <flux:text>{{ __('Datos personales y matrículas del estudiante.') }}</flux:text>
            </div>

            <flux:separator variant="subtle" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="first_name" :label="__('Nombres')" />
                <flux:input wire:model="last_name" :label="__('Apellidos')" />
                <flux:input wire:model="document" :label="__('Documento')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
                <flux:input wire:model="phone" :label="__('Teléfono')" />
                <flux:input wire:model="username" :label="__('Usuario')" />
                <flux:input wire:model="city" :label="__('Ciudad')" />
            </div>

            {{-- Enrollments --}}
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <flux:label>{{ __('Matrículas') }}</flux:label>
                    <flux:button wire:click="addEnrollment" size="xs" variant="ghost" icon="plus">{{ __('Agregar') }}</flux:button>
                </div>
                @error('enrollments')<flux:text class="mb-2 text-red-500">{{ $message }}</flux:text>@enderror

                <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    @forelse ($enrollments as $index => $enrollment)
                        @php($selectedCycle = $cycles->firstWhere('id', $enrollment['cycle_id']))
                        <div class="flex items-end gap-2">
                            <flux:select wire:model.live="enrollments.{{ $index }}.cycle_id" :label="__('Ciclo')" size="sm" class="flex-1">
                                <flux:select.option :value="null">{{ __('—') }}</flux:select.option>
                                @foreach ($cycles as $cycle)
                                    <flux:select.option :value="$cycle->id">{{ $cycle->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="enrollments.{{ $index }}.group_id" :label="__('Grupo')" size="sm" class="flex-1" :disabled="! $selectedCycle">
                                <flux:select.option :value="null">{{ __('Sin grupo') }}</flux:select.option>
                                @foreach ($selectedCycle?->groups ?? [] as $group)
                                    <flux:select.option :value="$group->id">{{ $group->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:button wire:click="removeEnrollment({{ $index }})" size="sm" icon="trash" variant="subtle" inset="top bottom" />
                        </div>
                    @empty
                        <flux:text size="sm" class="italic text-zinc-400">{{ __('Sin matrículas. Usa "Agregar" para matricular en un ciclo.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
