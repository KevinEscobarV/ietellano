@props(['name'])

{{-- Vuelve a abrir el tutorial <x-tour> del mismo nombre. --}}
<flux:button
    size="sm"
    variant="ghost"
    icon="question-mark-circle"
    x-data
    x-on:click="$dispatch('tour-start', { name: '{{ $name }}' })"
    data-tour="help"
    {{ $attributes }}
>{{ __('¿Cómo funciona?') }}</flux:button>
