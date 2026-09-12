<!DOCTYPE html>
{{-- La consulta pública del estudiante: una sola columna, clara y lista para
     imprimir. Es la única pantalla que no sigue la preferencia de tema del
     dispositivo —un boletín en papel no se imprime en modo oscuro—, así que
     omite el script de apariencia y pinta sus colores de forma explícita. --}}
<html lang="es" class="scheme-light bg-zinc-100 antialiased">
    <head>
        @include('partials.head', ['appearance' => false])
    </head>
    <body class="flex min-h-svh flex-col bg-zinc-100 font-sans text-zinc-900 print:bg-white">
        <header class="border-b border-zinc-200 bg-white print:hidden">
            <div class="mx-auto flex w-full max-w-3xl items-center gap-3 px-5 py-3">
                <img
                    src="{{ asset('images/logo-web.png') }}"
                    width="400"
                    height="402"
                    alt="Escudo de la Institución Educativa Técnica Empresarial del Llano"
                    class="h-9 w-auto"
                >
                <div class="leading-tight">
                    <div class="text-sm font-semibold">IETE del Llano</div>
                    <div class="text-xs text-zinc-500">Consulta de boletines</div>
                </div>

                <a
                    href="{{ route('home') }}"
                    class="ml-auto rounded-lg px-2.5 py-1.5 text-sm font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
                    wire:navigate
                >
                    Inicio
                </a>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl grow px-5 py-8 print:max-w-none print:p-0">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 py-5 text-center text-xs text-zinc-500 print:hidden">
            Institución Educativa Técnica Empresarial del Llano &middot; Tauramena, Casanare
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
