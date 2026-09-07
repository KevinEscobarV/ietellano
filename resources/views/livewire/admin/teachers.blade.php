<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="user-group" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Docentes') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Administrar docentes y los cursos que dictan') }}</flux:subheading>
        </div>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            {{ __('Nuevo docente') }}
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <flux:input
            wire:model.live.debounce="search"
            icon="magnifying-glass"
            :placeholder="__('Buscar por nombre o email...')"
            class="max-w-sm"
        />
    </div>

    {{-- Table --}}
    <flux:table :paginate="$teachers">
        <flux:table.columns>
            <flux:table.column>{{ __('Docente') }}</flux:table.column>
            <flux:table.column>{{ __('Teléfono') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Cursos') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($teachers as $teacher)
                <flux:table.row :key="$teacher->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$teacher->name" />
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $teacher->name }}</div>
                                <div class="text-sm text-zinc-500">{{ $teacher->email ?? '—' }}</div>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $teacher->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $teacher->courses_count }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button wire:click="openEdit({{ $teacher->id }})" size="sm" icon="pencil" variant="ghost" inset="top bottom" :tooltip="__('Editar')" />
                            <flux:button wire:click="openDelete({{ $teacher->id }})" size="sm" icon="trash" variant="danger" inset="top bottom" :tooltip="__('Eliminar')" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <div class="py-12 text-center">
                            <flux:icon name="user-group" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:heading size="sm" class="mb-1 text-zinc-500">{{ __('No hay docentes') }}</flux:heading>
                            <flux:text size="sm" class="text-zinc-400">
                                {{ $search ? __('Ningún docente coincide con la búsqueda.') : __('Crea el primer docente.') }}
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
                <flux:heading size="lg">{{ __('¿Eliminar docente?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Los cursos que dictaba quedarán sin docente asignado. Esta acción no se puede deshacer.') }}</flux:text>
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
                <flux:heading size="lg">{{ $editingId ? __('Editar docente') : __('Nuevo docente') }}</flux:heading>
                <flux:text>{{ __('Datos del docente y cursos que dicta.') }}</flux:text>
            </div>

            <flux:separator variant="subtle" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="first_name" :label="__('Nombres')" />
                <flux:input wire:model="last_name" :label="__('Apellidos')" />
                <flux:input wire:model="username" :label="__('Usuario')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
                <flux:input wire:model="phone" :label="__('Teléfono')" />
            </div>

            {{-- Assigned courses --}}
            <div>
                <flux:label class="mb-2 block">{{ __('Cursos asignados') }} ({{ count($assignedCourseIds) }})</flux:label>
                @if ($assignedCourses->isEmpty())
                    <flux:text size="sm" class="italic text-zinc-400">{{ __('Ningún curso asignado todavía.') }}</flux:text>
                @else
                    <div class="flex flex-wrap gap-1">
                        @foreach ($assignedCourses as $course)
                            <flux:badge size="sm" color="emerald" inset="top bottom">
                                {{ $course->cycle->name }} · {{ $course->subject->name }} (P{{ $course->period }})
                            </flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Course picker --}}
            <div>
                <flux:select wire:model.live="filterCycleId" :label="__('Asignar cursos por ciclo')" :placeholder="__('Selecciona un ciclo')" size="sm">
                    @foreach ($cycles as $cycle)
                        <flux:select.option :value="$cycle->id">{{ $cycle->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($filterCourses->isNotEmpty())
                    <div class="mt-3 grid max-h-64 gap-1.5 overflow-y-auto rounded-lg border border-zinc-200 p-3 sm:grid-cols-2 dark:border-zinc-700">
                        @foreach ($filterCourses as $course)
                            @php($courseLabel = $course->subject->name.' (P'.$course->period.')'.($course->group ? ' · '.$course->group->name : '').($course->teacher && $course->teacher_id !== $editingId ? ' · '.__('actual').': '.$course->teacher->name : ''))
                            <flux:checkbox
                                wire:model.live="assignedCourseIds"
                                :value="$course->id"
                                :label="$courseLabel"
                            />
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
