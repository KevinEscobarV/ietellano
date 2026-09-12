<div class="w-full">
    <div class="mb-4 flex items-center justify-between gap-4">
        <flux:input wire:model.live.debounce="search" icon="magnifying-glass" :placeholder="__('Buscar por código...')" class="max-w-xs" />
        <flux:button wire:click="openCreate" variant="primary" icon="plus" size="sm">{{ __('Nuevo curso') }}</flux:button>
    </div>

    <flux:table :paginate="$courses">
        <flux:table.columns>
            <flux:table.column>{{ __('Código') }}</flux:table.column>
            <flux:table.column>{{ __('Ciclo') }}</flux:table.column>
            <flux:table.column>{{ __('Materia') }}</flux:table.column>
            <flux:table.column>{{ __('Grupo') }}</flux:table.column>
            <flux:table.column>{{ __('Docente') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Periodo') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($courses as $course)
                <flux:table.row :key="$course->id">
                    <flux:table.cell><flux:badge size="sm" color="zinc" inset="top bottom">{{ $course->code }}</flux:badge></flux:table.cell>
                    <flux:table.cell>{{ $course->cycle->label }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $course->subject->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $course->group->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $course->teacher->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="center">{{ $course->period }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $course->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" />
                            <flux:button wire:click="openDelete({{ $course->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7"><div class="py-8 text-center text-zinc-400">{{ __('No hay cursos.') }}</div></flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Eliminar curso?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Se eliminarán las notas de este curso. Esta acción no se puede deshacer.') }}</flux:text>
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
            <flux:heading size="lg">{{ $editingId ? __('Editar curso') : __('Nuevo curso') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="code" :label="__('Código')" placeholder="C5MATM1" />
                <flux:select wire:model="period" :label="__('Periodo')">
                    <flux:select.option :value="1">1</flux:select.option>
                    <flux:select.option :value="2">2</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="cycle_id" :label="__('Ciclo')" :placeholder="__('Selecciona')">
                    @foreach ($cycles as $cycle)
                        <flux:select.option :value="$cycle->id">{{ $cycle->label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="subject_id" :label="__('Materia')" :placeholder="__('Selecciona')">
                    @foreach ($subjects as $subject)
                        <flux:select.option :value="$subject->id">{{ $subject->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="group_id" :label="__('Grupo')" :placeholder="__('Sin grupo')" :disabled="! $cycle_id">
                    <flux:select.option :value="null">{{ __('Sin grupo') }}</flux:select.option>
                    @foreach ($cycleGroups as $group)
                        <flux:select.option :value="$group->id">{{ $group->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="teacher_id" :label="__('Docente')" :placeholder="__('Sin docente')">
                    <flux:select.option :value="null">{{ __('Sin docente') }}</flux:select.option>
                    @foreach ($teachers as $teacher)
                        <flux:select.option :value="$teacher->id">{{ $teacher->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
