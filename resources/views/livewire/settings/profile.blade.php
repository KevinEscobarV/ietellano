<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout
        :heading="__('Profile')"
        :subheading="$this->canEdit ? __('Update your name and email address') : __('Tus datos personales')"
    >
        @if ($this->canEdit)
            <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div>
                            <flux:text class="mt-4">
                                {{ __('Your email address is unverified.') }}

                                <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Click here to re-send the verification email.') }}
                                </flux:link>
                            </flux:text>

                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        @else
            {{-- El nombre y el correo del docente son los mismos que salen en
                 boletines y planillas, así que los lleva la coordinación. --}}
            <div class="my-6 w-full space-y-6">
                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div>
                        <div class="text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Name') }}</div>
                        <div class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $name }}</div>
                    </div>

                    <flux:separator variant="subtle" />

                    <div>
                        <div class="text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Email') }}</div>
                        <div class="mt-1 break-all text-zinc-900 dark:text-zinc-100">{{ $email }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                    <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                    <flux:text class="text-sm">
                        {{ __('Estos son los datos con los que apareces en planillas y boletines, así que los actualiza la coordinación. Si hay algo mal escrito, avísales.') }}
                    </flux:text>
                </div>

                <flux:text class="text-sm">
                    {{ __('Tu contraseña sí la cambias tú:') }}
                    <flux:link :href="route('security.edit')" wire:navigate class="text-sm">{{ __('Cambiar contraseña') }}</flux:link>
                </flux:text>
            </div>
        @endif

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
