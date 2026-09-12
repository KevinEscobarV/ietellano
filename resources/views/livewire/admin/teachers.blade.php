<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="user-group" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Docentes') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Administrar docentes, sus cursos y su acceso al sistema') }}</flux:subheading>
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
            <flux:table.column>{{ __('Acceso') }}</flux:table.column>
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
                        @if ($teacher->user)
                            <flux:dropdown position="bottom" align="start">
                                <flux:button size="sm" variant="ghost" icon-trailing="chevron-down" inset="top bottom">
                                    <flux:badge size="sm" color="emerald" inset="top bottom">{{ __('Activo') }}</flux:badge>
                                </flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="key" wire:click="resetAccessPassword({{ $teacher->id }})">
                                        {{ __('Generar nueva contraseña') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="no-symbol" variant="danger" wire:click="revokeAccess({{ $teacher->id }})">
                                        {{ __('Quitar acceso') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @elseif ($teacher->email)
                            <flux:button size="sm" variant="filled" icon="key" inset="top bottom" wire:click="createAccess({{ $teacher->id }})">
                                {{ __('Crear acceso') }}
                            </flux:button>
                        @else
                            <flux:tooltip :content="__('Necesita un email para poder entrar')">
                                <flux:badge size="sm" color="zinc" variant="outline" inset="top bottom">{{ __('Sin email') }}</flux:badge>
                            </flux:tooltip>
                        @endif
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
                    <flux:table.cell colspan="5">
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

    {{-- Credenciales recién generadas --}}
    <flux:modal wire:model="showAccessModal" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Acceso listo') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Entrégale estos datos a :name. La contraseña no se vuelve a mostrar.', ['name' => $accessName]) }}
                </flux:text>
            </div>

            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Usuario') }}</div>
                    <div class="mt-0.5 font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $accessEmail }}</div>
                </div>

                @if ($accessPassword !== '')
                    <flux:separator variant="subtle" />

                    <div x-data="{ copied: false }">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Contraseña temporal') }}</div>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="flex-1 rounded-lg bg-zinc-100 px-3 py-2 font-mono text-base tracking-wider text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ $accessPassword }}</code>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="clipboard"
                                x-on:click="navigator.clipboard.writeText(@js($accessPassword)); copied = true; setTimeout(() => copied = false, 1600)"
                                :tooltip="__('Copiar')"
                            />
                        </div>
                        <p x-show="copied" x-transition.opacity x-cloak class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                            {{ __('Copiada') }}
                        </p>
                    </div>
                @else
                    <flux:separator variant="subtle" />
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Esa cuenta ya existía en el sistema, así que conserva su contraseña actual.') }}
                    </flux:text>
                @endif
            </div>

            <flux:callout variant="secondary" icon="information-circle">
                {{ __('Pídele que la cambie desde Ajustes la primera vez que entre.') }}
            </flux:callout>

            <div class="flex justify-end">
                <flux:button wire:click="$set('showAccessModal', false)" variant="primary">{{ __('Listo') }}</flux:button>
            </div>
        </div>
    </flux:modal>

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
                <flux:input wire:model="email" type="email" :label="__('Email')" :description="__('Con este email entra al sistema.')" />
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
