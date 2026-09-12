{{-- El botón principal de las pantallas de acceso: el mismo verde de la portada. --}}
<button
    type="submit"
    {{ $attributes->class('llano-button flex w-full items-center justify-center gap-2.5 rounded-xl px-6 py-3 text-[15px] font-semibold tracking-tight text-[#04120B] focus-visible:outline-2 focus-visible:outline-offset-[3px] focus-visible:outline-llano-emerald') }}
>
    {{ $slot }}
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M5 12h13M13 6l6 6-6 6" />
    </svg>
</button>
