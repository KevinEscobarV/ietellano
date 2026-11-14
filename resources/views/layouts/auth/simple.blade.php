<!DOCTYPE html>
{{-- Las pantallas de acceso visten como el panel: lienzo gris, tarjeta blanca
     y, a un lado, la misma portada azul del Inicio (resources/css/panel.css).
     Siguen el tema claro/oscuro del dispositivo, igual que el panel. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-svh flex-col items-center justify-center gap-6 bg-zinc-100 px-4 py-8 font-sans sm:px-6 dark:bg-zinc-950">
        <main class="panel-card dash-rise grid w-full max-w-md overflow-hidden rounded-2xl lg:max-w-4xl lg:grid-cols-[5fr_6fr]">
            {{-- La cara de la institución; en pantallas angostas se resume arriba del formulario. --}}
            <div class="dash-hero relative hidden flex-col justify-between gap-10 overflow-hidden p-10 text-white lg:flex">
                <div class="dash-hero-glow" aria-hidden="true"><b></b><b></b><b></b></div>

                <a href="{{ route('home') }}" class="relative flex items-center gap-3" wire:navigate>
                    <img
                        src="{{ asset('images/logo-web.png') }}"
                        width="400"
                        height="402"
                        alt="{{ __('Escudo de la institución') }}"
                        class="h-auto w-12 drop-shadow-[0_6px_16px_rgb(0_0_0/.35)]"
                    >
                    <span class="text-lg font-bold tracking-tight">IETE del Llano</span>
                </a>

                <div class="relative">
                    <h2 class="text-3xl leading-tight font-bold tracking-tight text-balance">{{ __('Sistema académico') }}</h2>
                    <p class="mt-3 leading-relaxed text-pretty text-white/80">
                        {{ __('Notas, asistencia, boletines y certificados de estudio, en un solo lugar.') }}
                    </p>
                </div>

                <p class="relative text-[10px] font-medium tracking-[.14em] whitespace-nowrap text-white/60 uppercase">
                    Responsabilidad &middot; Compromiso &middot; Respeto
                </p>
            </div>

            <div class="flex flex-col justify-center gap-6 p-6 sm:p-10">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 lg:hidden" wire:navigate>
                    <img
                        src="{{ asset('images/logo-web.png') }}"
                        width="400"
                        height="402"
                        alt="{{ __('Escudo de la institución') }}"
                        class="h-auto w-14"
                    >
                    <span class="text-lg font-bold tracking-tight text-zinc-900 dark:text-zinc-100">IETE del Llano</span>
                </a>

                {{ $slot }}
            </div>
        </main>

        <p class="text-[10.5px] font-medium tracking-[.28em] text-zinc-400 uppercase lg:hidden dark:text-zinc-500">
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
