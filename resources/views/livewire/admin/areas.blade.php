<div class="w-full">
    <div class="mb-4 flex items-center justify-end">
        <flux:button wire:click="openCreate" variant="primary" icon="plus" size="sm">{{ __('Nueva área') }}</flux:button>
    </div>

    <flux:table :paginate="$areas">
        <flux:table.columns>
            <flux:table.column>{{ __('Ciclo') }}</flux:table.column>
            <flux:table.column>{{ __('Área') }}</flux:table.column>
            <flux:table.column>{{ __('Materias combinadas') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($areas as $area)
                <flux:table.row :key="$area->id">
                    <flux:table.cell>{{ $area->cycle->name }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $area->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($area->subjects as $subject)
                                <flux:badge size="sm" color="zinc" inset="top bottom">{{ $subject->name }}</flux:badge>
                            @empty
                                <flux:text size="sm" class="italic text-zinc-400">{{ __('Sin materias') }}</flux:text>
                            @endforelse
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $area->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" />
                            <flux:button wire:click="openDelete({{ $area->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="4"><div class="py-8 text-center text-zinc-400">{{ __('No hay áreas.') }}</div></flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar área?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Las materias volverán a mostrarse por separado en el boletín. Esta acción no se puede deshacer.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancelar') }}</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Editar área') : __('Nueva área') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="cycle_id" :label="__('Ciclo')" :placeholder="__('Selecciona')">
                    @foreach ($cycles as $cycle)
                        <flux:select.option :value="$cycle->id">{{ $cycle->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('Nombre')" placeholder="Ciencias Naturales" />
                <flux:input wire:model="code" :label="__('Código')" :placeholder="__('Opcional')" />
                <flux:input wire:model="display_order" type="number" :label="__('Orden')" />
            </div>

            <div>
                <flux:label class="mb-2 block">{{ __('Materias que combina') }}</flux:label>
                @if (! $cycle_id)
                    <flux:text size="sm" class="italic text-zinc-400">{{ __('Selecciona un ciclo para ver sus materias.') }}</flux:text>
                @elseif ($cycleSubjects->isEmpty())
                    <flux:text size="sm" class="italic text-zinc-400">{{ __('Este ciclo no tiene materias.') }}</flux:text>
                @else
                    <div class="grid gap-1.5 rounded-lg border border-zinc-200 p-3 sm:grid-cols-2 dark:border-zinc-700">
                        @foreach ($cycleSubjects as $subject)
                            <flux:checkbox wire:model="subjectIds" :value="$subject->id" :label="$subject->name" />
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
