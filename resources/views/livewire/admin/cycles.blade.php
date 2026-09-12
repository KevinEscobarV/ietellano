<div class="w-full">
    <div class="mb-4 flex items-center justify-end">
        <flux:button wire:click="openCreate" variant="primary" icon="plus" size="sm">{{ __('Nuevo ciclo') }}</flux:button>
    </div>

    <flux:table :paginate="$cycles">
        <flux:table.columns>
            <flux:table.column>{{ __('Ciclo') }}</flux:table.column>
            <flux:table.column>{{ __('Código') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Sem.') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Año') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Grupos') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Estud.') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($cycles as $cycle)
                <flux:table.row :key="$cycle->id">
                    <flux:table.cell class="font-medium">
                        {{ $cycle->name }}
                        @if ($cycle->isClosed())
                            <flux:badge size="sm" color="rose" inset="top bottom">{{ __('Cerrado') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" color="zinc" inset="top bottom">{{ $cycle->code }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="center">{{ $cycle->semester }}</flux:table.cell>
                    <flux:table.cell align="center">{{ $cycle->year }}</flux:table.cell>
                    <flux:table.cell align="center">{{ $cycle->groups_count }}</flux:table.cell>
                    <flux:table.cell align="center">{{ $cycle->students_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $cycle->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" />
                            <flux:button wire:click="openDelete({{ $cycle->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7"><div class="py-8 text-center text-zinc-400">{{ __('No hay ciclos.') }}</div></flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar ciclo?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Se eliminarán grupos, cursos, matrículas y áreas del ciclo. Esta acción no se puede deshacer.') }}</flux:text>
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
            <flux:heading size="lg">{{ $editingId ? __('Editar ciclo') : __('Nuevo ciclo') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Nombre')" placeholder="Ciclo 5" />
                <flux:input wire:model="code" :label="__('Código')" placeholder="Ciclo5S12026" />
                <flux:input wire:model="level" :label="__('Nivel')" placeholder="5" />
                <flux:input wire:model="semester" type="number" :label="__('Semestre')" />
                <flux:input wire:model="year" type="number" :label="__('Año')" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
