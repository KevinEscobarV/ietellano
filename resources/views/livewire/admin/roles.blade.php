<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="shield-check" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Roles') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Define access levels and assign permissions to each role') }}</flux:subheading>
        </div>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            {{ __('New Role') }}
        </flux:button>
    </div>

    {{-- Roles Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell variant="strong">{{ $role->name }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($role->permissions->isEmpty())
                            <flux:text size="sm" class="italic text-zinc-400">{{ __('No permissions assigned') }}</flux:text>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach ($role->permissions->sortBy('name') as $permission)
                                    <flux:badge size="sm" variant="outline" color="zinc" inset="top bottom">{{ $permission->name }}</flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button
                                wire:click="openEdit({{ $role->id }})"
                                size="sm"
                                icon="pencil"
                                variant="ghost"
                                inset="top bottom"
                                :tooltip="__('Edit')"
                            />
                            <flux:button
                                wire:click="openDelete({{ $role->id }})"
                                size="sm"
                                icon="trash"
                                variant="danger"
                                inset="top bottom"
                                :tooltip="__('Delete')"
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <div class="py-12 text-center">
                            <flux:icon name="shield-off" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:heading size="sm" class="mb-1 text-zinc-500">{{ __('No roles yet') }}</flux:heading>
                            <flux:text size="sm" class="mb-4 text-zinc-400">{{ __('Create your first role to start managing access.') }}</flux:text>
                            <flux:button wire:click="openCreate" variant="ghost" size="sm" icon="plus">
                                {{ __('Create Role') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete role?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('You\'re about to delete this role.') }}<br>
                    {{ __('Users assigned to it will lose this access level.') }}
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="delete" variant="danger" icon="trash">
                    {{ __('Delete Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Create / Edit Modal --}}
    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div class="flex flex-col items-center space-y-4">
                <div class="w-auto rounded-full border border-zinc-100 bg-white p-0.5 shadow-sm dark:border-zinc-600 dark:bg-zinc-800">
                    <div class="relative overflow-hidden rounded-full border border-zinc-200 bg-zinc-100 p-2.5 dark:border-zinc-600 dark:bg-zinc-200">
                        <div class="absolute inset-0 flex h-full w-full items-stretch divide-x divide-zinc-200 opacity-50 dark:divide-zinc-300 [&>div]:flex-1">
                            @for ($i = 1; $i <= 5; $i++)<div></div>@endfor
                        </div>
                        <div class="absolute inset-0 flex h-full w-full flex-col items-stretch divide-y divide-zinc-200 opacity-50 dark:divide-zinc-300 [&>div]:flex-1">
                            @for ($i = 1; $i <= 5; $i++)<div></div>@endfor
                        </div>
                        @if ($editingId)
                            <flux:icon name="pencil" class="relative z-20 dark:text-zinc-800" />
                        @else
                            <flux:icon name="shield-check" class="relative z-20 dark:text-zinc-800" />
                        @endif
                    </div>
                </div>

                <div class="space-y-1 text-center">
                    <flux:heading size="lg">
                        {{ $editingId ? __('Edit Role') : __('New Role') }}
                    </flux:heading>
                    <flux:text>
                        {{ $editingId
                            ? __('Update the name and permissions for this role.')
                            : __('Give this role a name and select the permissions it should have.') }}
                    </flux:text>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <flux:input
                wire:model="name"
                :label="__('Role Name')"
                :placeholder="__('e.g. moderator')"
                :description="__('Lowercase, hyphens and underscores only.')"
                autofocus
            />

            <div>
                <flux:label class="block">{{ __('Permissions') }}</flux:label>
                <div class="space-y-5 rounded-lg border border-zinc-200 p-4 mt-3 dark:border-zinc-700">
                    @foreach ($allPermissions as $group => $groupPermissions)
                        <div class="space-y-4">
                            <div class="relative flex items-center justify-center w-full">
                                <div class="absolute inset-0 w-full h-px top-1/2 bg-stone-200 dark:bg-stone-600"></div>
                                <span class="relative px-2 text-sm bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-400">
                                    {{ $group }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                @foreach ($groupPermissions as $permission)
                                    <flux:checkbox
                                        wire:model="selectedPermissions"
                                        :value="$permission->name"
                                        :label="$permission->name"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Save Role') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
