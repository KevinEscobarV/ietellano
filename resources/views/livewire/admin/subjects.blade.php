<div class="w-full">
    <div class="mb-4 flex items-center justify-between gap-4">
        <flux:input wire:model.live.debounce="search" icon="magnifying-glass" :placeholder="__('Buscar materia...')" class="max-w-xs" />
        <flux:button wire:click="openCreate" variant="primary" icon="plus" size="sm">{{ __('Nueva materia') }}</flux:button>
    </div>

    <flux:table :paginate="$subjects">
        <flux:table.columns>
            <flux:table.column>{{ __('Código') }}</flux:table.column>
            <flux:table.column>{{ __('Materia') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Cursos') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($subjects as $subject)
                <flux:table.row :key="$subject->id">
                    <flux:table.cell><flux:badge size="sm" color="zinc" inset="top bottom">{{ $subject->code }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $subject->name }}</flux:table.cell>
                    <flux:table.cell align="center">{{ $subject->courses_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $subject->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" />
                            <flux:button wire:click="openDelete({{ $subject->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="4"><div class="py-8 text-center text-zinc-400">{{ __('No hay materias.') }}</div></flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar materia?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Se eliminarán también sus cursos asociados. Esta acción no se puede deshacer.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancelar') }}</flux:button></flux:modal.close>
                <flux:button wire:click="delete" variant="danger" icon="trash">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Editar materia') : __('Nueva materia') }}</flux:heading>
            <flux:input wire:model="code" :label="__('Código')" placeholder="MAT" />
            <flux:input wire:model="name" :label="__('Nombre')" placeholder="Matemáticas" />
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
