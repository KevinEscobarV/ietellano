<!DOCTYPE html>
{{-- El contenido de esta portada está redactado en español, sin traducciones. --}}
<html lang="es" class="scheme-dark bg-llano-void antialiased">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-svh flex-col items-center justify-center gap-7 overflow-x-hidden bg-llano-void px-5 py-10 font-sans text-llano-mist">
    <x-llano-backdrop />

    <main class="llano-card relative z-10 flex w-[min(492px,100%)] flex-col items-center gap-6 rounded-3xl px-[clamp(24px,6vw,40px)] pt-[clamp(32px,6vw,46px)] pb-[clamp(28px,5vw,36px)] text-center">
        <div class="animate-llano-rise relative [animation-delay:.34s]">
            <span class="pointer-events-none absolute -inset-[30%] rounded-full bg-[radial-gradient(circle,rgb(31_190_123/.22)_0%,transparent_68%)]" aria-hidden="true"></span>
            <img
                src="{{ asset('images/logo-web.png') }}"
                width="400"
                height="402"
                alt="Escudo de la Institución Educativa Técnica Empresarial del Llano"
                class="relative h-auto w-[clamp(94px,25vw,112px)] drop-shadow-[0_8px_22px_rgb(0_0_0/.55)]"
                fetchpriority="high"
            >
        </div>

        <div class="flex flex-col items-center gap-2.5">
            <h1 class="llano-title animate-llano-rise text-[clamp(34px,8vw,52px)] leading-[1.02] font-bold tracking-[-.035em] [animation-delay:.44s]">
                IETE del Llano
            </h1>

            <p class="animate-llano-rise text-[13px] leading-normal text-balance text-llano-haze [animation-delay:.52s]">
                Institución Educativa Técnica Empresarial del Llano<br>
                Tauramena, Casanare
            </p>
        </div>

        <div class="llano-divider animate-llano-rise w-full [animation-delay:.6s]" aria-hidden="true"></div>

        <p class="animate-llano-rise text-[14.5px] leading-relaxed text-pretty text-llano-mist/90 [animation-delay:.66s]">
            Sistema académico: registro de notas, boletines de calificaciones y certificados de estudio.
        </p>

        <div class="animate-llano-rise flex w-full flex-col gap-3 [animation-delay:.76s]">
            <a
                href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                class="llano-button flex w-full items-center justify-center gap-2.5 rounded-xl px-6 py-3.5 text-[15px] font-semibold tracking-tight text-[#04120B] focus-visible:outline-2 focus-visible:outline-offset-[3px] focus-visible:outline-llano-emerald"
            >
                {{ auth()->check() ? 'Ir al panel' : 'Ingresar al sistema' }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h13M13 6l6 6-6 6" />
                </svg>
            </a>

            {{-- La puerta del estudiante: sin cuenta, solo su documento. --}}
            <a
                href="{{ route('consulta.lookup') }}"
                class="flex w-full items-center justify-center gap-2.5 rounded-xl border border-white/15 bg-white/[.06] px-6 py-3.5 text-[15px] font-semibold tracking-tight text-llano-mist transition hover:border-white/30 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-[3px] focus-visible:outline-llano-emerald"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6z" />
                    <path d="M14 2v5h5M9 13h6M9 17h4" />
                </svg>
                Consultar mi boletín
            </a>
        </div>

        <p class="animate-llano-rise text-xs leading-normal text-pretty text-llano-haze/85 [animation-delay:.84s]">
            Si eres estudiante, consulta tu boletín con tu documento de identidad: no necesitas usuario ni contraseña.
            El ingreso con cuenta está reservado a docentes y personal administrativo, y los certificados de estudio
            se solicitan en la institución.
        </p>
    </main>

    <p class="animate-llano-rise relative z-10 text-[10.5px] font-medium tracking-[.28em] text-llano-haze/60 uppercase [animation-delay:.96s]">
        Responsabilidad &middot; Compromiso &middot; Respeto
    </p>
</body>
</html>
