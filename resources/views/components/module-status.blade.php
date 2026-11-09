@props(['module', 'state', 'at' => null])

{{-- El sello de un módulo de un ciclo: abierto, cerrado o con fecha de cierre.
     Lo dice con palabras y no solo con color, porque es lo que decide si el
     docente todavía puede escribir. --}}

@php
    [$color, $icon, $text] = match ($state) {
        'closed' => ['zinc', 'lock-closed', __('M:n cerrado', ['n' => $module])],
        'scheduled' => ['amber', 'clock', __('M:n hasta el :date', ['n' => $module, 'date' => $at->translatedFormat('j M')])],
        default => ['emerald', 'lock-open', __('M:n abierto', ['n' => $module])],
    };
@endphp

<flux:badge size="sm" :color="$color" :icon="$icon" {{ $attributes }}>{{ $text }}</flux:badge>
