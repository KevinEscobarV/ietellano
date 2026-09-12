<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Consulta tu boletín')"
        :description="__('No necesitas usuario ni contraseña: escribe tu documento y tu apellido.')"
    />

    <form wire:submit="consultar" class="flex flex-col gap-6">
        <flux:input
            wire:model="document"
            :label="__('Documento de identidad')"
            :placeholder="__('Sin puntos ni espacios')"
            inputmode="numeric"
            autocomplete="off"
            autofocus
            required
        />

        <flux:input
            wire:model="lastName"
            :label="__('Apellido')"
            :placeholder="__('Como aparece en tu documento')"
            autocomplete="off"
            required
        />

        <x-llano-submit wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="consultar">{{ __('Ver mi boletín') }}</span>
            <span wire:loading wire:target="consultar">{{ __('Buscando…') }}</span>
        </x-llano-submit>
    </form>

    <p class="text-center text-xs leading-relaxed text-llano-haze/85">
        {{ __('Aquí solo encuentras tu boletín de notas. Los certificados de estudio se siguen solicitando en la institución.') }}
    </p>
</div>
