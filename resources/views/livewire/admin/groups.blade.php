<div class="w-full">
    <div class="mb-4 flex items-center justify-end">
        <flux:button wire:click="openCreate" variant="primary" icon="plus" size="sm">{{ __('Nuevo grupo') }}</flux:button>
    </div>

    <flux:table :paginate="$groups">
        <flux:table.columns>
            <flux:table.column>{{ __('Ciclo') }}</flux:table.column>
            <flux:table.column>{{ __('Grupo') }}</flux:table.column>
            <flux:table.column>{{ __('Código') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Matrículas') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($groups as $group)
                <flux:table.row :key="$group->id">
                    <flux:table.cell>{{ $group->cycle->label }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $group->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $group->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="center">{{ $group->enrollments_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $group->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" />
                            <flux:button wire:click="openDelete({{ $group->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="5"><div class="py-8 text-center text-zinc-400">{{ __('No hay grupos.') }}</div></flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar grupo?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Las matrículas y cursos de este grupo quedarán sin grupo. Esta acción no se puede deshacer.') }}</flux:text>
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
            <flux:heading size="lg">{{ $editingId ? __('Editar grupo') : __('Nuevo grupo') }}</flux:heading>
            <flux:select wire:model="cycle_id" :label="__('Ciclo')" :placeholder="__('Selecciona un ciclo')">
                @foreach ($cycles as $cycle)
                    <flux:select.option :value="$cycle->id">{{ $cycle->label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="name" :label="__('Nombre')" placeholder="Ciclo 5-1" />
            <flux:input wire:model="code" :label="__('Código')" :placeholder="__('Opcional')" />
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
