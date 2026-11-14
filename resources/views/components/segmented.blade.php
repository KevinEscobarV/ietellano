@props(['options', 'value', 'property', 'label', 'track' => 'bg-zinc-100 dark:bg-zinc-800'])

{{-- Botones pegados para elegir una sola opción, como las pestañas de
     «Módulo 1 · Módulo 2 · Semestre». Cada botón pone la propiedad de Livewire
     con wire:click, sin formulario. `track` es el fondo del riel: sobre una
     página gris conviene uno más oscuro. --}}

<div {{ $attributes->class(['inline-flex flex-wrap rounded-lg p-1', $track]) }} role="group" aria-label="{{ $label }}">
    @foreach ($options as $key => $text)
        <button
            type="button"
            wire:key="{{ $property }}-{{ $key }}"
            wire:click="$set('{{ $property }}', '{{ $key }}')"
            aria-pressed="{{ (string) $key === (string) $value ? 'true' : 'false' }}"
            @class([
                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' => (string) $key === (string) $value,
                'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => (string) $key !== (string) $value,
            ])
        >{{ $text }}</button>
    @endforeach
</div>
