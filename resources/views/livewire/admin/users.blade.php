<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="users" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Manage user accounts and assign roles') }}</flux:subheading>
        </div>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            {{ __('New User') }}
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <flux:input
            wire:model.live.debounce="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name or email...')"
            class="max-w-sm"
        />
    </div>

    {{-- Users Table --}}
    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>{{ __('User') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column>{{ __('Joined') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$user->name" />
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $user->name }}</div>
                                <div class="text-sm text-zinc-500">{{ $user->email }}</div>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($user->roles->isEmpty())
                            <flux:text size="sm" class="italic text-zinc-400">{{ __('No roles assigned') }}</flux:text>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->roles->sortBy('name') as $role)
                                    <flux:badge size="sm" variant="outline" color="zinc" inset="top bottom">{{ $role->name }}</flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:text size="sm" class="text-zinc-500">{{ $user->created_at->format('M j, Y') }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button
                                wire:click="openEdit({{ $user->id }})"
                                size="sm"
                                icon="pencil"
                                variant="ghost"
                                inset="top bottom"
                                :tooltip="__('Edit')"
                            />
                            @unless ($user->id === auth()->id())
                                <flux:button
                                    wire:click="openDelete({{ $user->id }})"
                                    size="sm"
                                    icon="trash"
                                    variant="danger"
                                    inset="top bottom"
                                    :tooltip="__('Delete')"
                                />
                            @endunless
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <div class="py-12 text-center">
                            <flux:icon name="users" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:heading size="sm" class="mb-1 text-zinc-500">{{ __('No users found') }}</flux:heading>
                            <flux:text size="sm" class="mb-4 text-zinc-400">
                                @if ($search)
                                    {{ __('No users match your search.') }}
                                @else
                                    {{ __('Create your first user to get started.') }}
                                @endif
                            </flux:text>
                            @unless ($search)
                                <flux:button wire:click="openCreate" variant="ghost" size="sm" icon="plus">
                                    {{ __('Create User') }}
                                </flux:button>
                            @endunless
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
                <flux:heading size="lg">{{ __('Delete user?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('You\'re about to permanently delete this user.') }}<br>
                    {{ __('This action cannot be undone.') }}
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="delete" variant="danger" icon="trash">
                    {{ __('Delete User') }}
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
                            <flux:icon name="user-plus" class="relative z-20 dark:text-zinc-800" />
                        @endif
                    </div>
                </div>

                <div class="space-y-1 text-center">
                    <flux:heading size="lg">
                        {{ $editingId ? __('Edit User') : __('New User') }}
                    </flux:heading>
                    <flux:text>
                        {{ $editingId
                            ? __('Update the user\'s details and assigned roles.')
                            : __('Create a new user account and assign roles.') }}
                    </flux:text>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <flux:input
                wire:model="name"
                :label="__('Name')"
                :placeholder="__('Full name')"
                autofocus
            />

            <flux:input
                wire:model="email"
                type="email"
                :label="__('Email')"
                :placeholder="__('email@example.com')"
            />

            <flux:input
                wire:model="password"
                type="password"
                :label="__('Password')"
                :placeholder="$editingId ? __('Leave blank to keep current') : __('Minimum 8 characters')"
                :description="$editingId ? __('Only fill this in if you want to change the password.') : ''"
            />

            <div>
                <flux:label class="mb-3 block">{{ __('Roles') }}</flux:label>
                <div class="space-y-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    @forelse ($allRoles as $role)
                        <flux:checkbox
                            wire:model="selectedRoles"
                            :value="$role->name"
                            :label="$role->name"
                        />
                    @empty
                        <flux:text size="sm" class="italic text-zinc-400">{{ __('No roles available. Create roles first.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="save" variant="primary" icon="check">{{ __('Save User') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
