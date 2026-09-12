<!DOCTYPE html>
{{-- Comparte el mundo visual de la portada: ver resources/css/llano.css. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scheme-dark bg-llano-void antialiased">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-svh flex-col items-center justify-center gap-6 overflow-x-hidden bg-llano-void px-5 py-10 font-sans text-llano-mist">
        <x-llano-backdrop />

        <main class="llano-card relative z-10 flex w-[min(440px,100%)] flex-col gap-7 rounded-3xl px-[clamp(24px,6vw,38px)] pt-[clamp(30px,6vw,42px)] pb-[clamp(26px,5vw,34px)]">
            <a href="{{ route('home') }}" class="animate-llano-rise flex flex-col items-center gap-3 [animation-delay:.3s]" wire:navigate>
                <span class="relative">
                    <span class="pointer-events-none absolute -inset-[35%] rounded-full bg-[radial-gradient(circle,rgb(31_190_123/.22)_0%,transparent_68%)]" aria-hidden="true"></span>
                    <img
                        src="{{ asset('images/logo-web.png') }}"
                        width="400"
                        height="402"
                        alt="{{ __('Escudo de la institución') }}"
                        class="relative h-auto w-16 drop-shadow-[0_8px_22px_rgb(0_0_0/.55)]"
                    >
                </span>
                <span class="llano-title text-xl font-bold tracking-tight">IETE del Llano</span>
            </a>

            <div class="llano-divider" aria-hidden="true"></div>

            <div class="animate-llano-rise flex flex-col gap-6 [animation-delay:.42s]">
                {{ $slot }}
            </div>
        </main>

        <p class="animate-llano-rise relative z-10 text-[10.5px] font-medium tracking-[.28em] text-llano-haze/60 uppercase [animation-delay:.6s]">
            Responsabilidad &middot; Compromiso &middot; Respeto
        </p>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
