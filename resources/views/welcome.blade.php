<!DOCTYPE html>
{{-- El contenido de esta portada está redactado en español, sin traducciones. --}}
<html lang="es" class="home-root antialiased">
<head>
    @include('partials.head')

    <style>
        /*
        | Esta portada se compromete con un solo mundo visual: la noche sobre el llano.
        | No sigue el tema claro/oscuro de la app — pinta todos sus colores de forma
        | explícita para verse igual venga de donde venga el visitante.
        */
        .home-root {
            --void:     #06100C;
            --jade:     #0E7A4E;
            --emerald:  #1FBE7B;
            --gold:     #F2B23E;
            --mist:     #DCEDE4;
            --mist-2:   #8FA79A;
            --glass:    rgb(255 255 255 / .055);
            --edge:     rgb(255 255 255 / .10);

            background: var(--void);
            color-scheme: dark;
        }

        .home {
            background: var(--void);
            color: var(--mist);
            overflow-x: hidden;
        }

        /* ---------- aurora: el degradado que respira detrás de todo ---------- */

        .aurora {
            position: fixed;
            inset: -25%;
            z-index: 0;
            filter: blur(72px);
            pointer-events: none;
            animation: wake 1.6s ease-out both;
        }

        .aurora b {
            position: absolute;
            display: block;
            border-radius: 50%;
            will-change: transform;
        }

        .aurora b:nth-child(1) {
            width: 54vw; height: 54vw; left: 6%; top: 4%;
            background: radial-gradient(circle, var(--jade) 0%, transparent 66%);
            animation: drift-a 38s ease-in-out infinite;
        }
        .aurora b:nth-child(2) {
            width: 46vw; height: 46vw; right: 2%; top: 20%;
            background: radial-gradient(circle, var(--emerald) 0%, transparent 64%);
            opacity: .55;
            animation: drift-b 47s ease-in-out infinite;
        }
        /* El sol del escudo, apenas insinuado en el horizonte. */
        .aurora b:nth-child(3) {
            width: 68vw; height: 38vw; left: 16%; bottom: -12%;
            background: radial-gradient(ellipse, var(--gold) 0%, transparent 62%);
            opacity: .26;
            animation: drift-c 56s ease-in-out infinite;
        }

        /* Grano finísimo: mata el bandeado de los degradados en pantallas oscuras. */
        .grain {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            opacity: .045;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* ---------- la tarjeta de vidrio ---------- */

        .card {
            position: relative;
            width: min(492px, 100%);
            padding: clamp(32px, 6vw, 46px) clamp(24px, 6vw, 40px) clamp(28px, 5vw, 36px);
            border-radius: 24px;
            background: linear-gradient(158deg, rgb(255 255 255 / .075), rgb(255 255 255 / .022));
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            backdrop-filter: blur(24px) saturate(150%);
            box-shadow:
                inset 0 1px 0 rgb(255 255 255 / .14),
                0 34px 80px -34px rgb(0 0 0 / .95);
            animation: card-in .92s cubic-bezier(.16, .84, .44, 1) both;
        }

        /* Borde luminoso: un degradado de 1px recortado con máscara. */
        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            padding: 1px;
            border-radius: inherit;
            background: linear-gradient(152deg, rgb(31 190 123 / .55) 0%, rgb(255 255 255 / .16) 30%, rgb(255 255 255 / .06) 100%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
            .card { background: linear-gradient(158deg, #12241B, #0B1712); }
        }

        .seal {
            width: clamp(94px, 25vw, 112px);
            height: auto;
            filter: drop-shadow(0 8px 22px rgb(0 0 0 / .55));
        }

        .crest-glow {
            position: absolute;
            inset: -30%;
            border-radius: 50%;
            background: radial-gradient(circle, rgb(31 190 123 / .22) 0%, transparent 68%);
            pointer-events: none;
        }

        .title {
            font-size: clamp(34px, 8vw, 52px);
            font-weight: 700;
            line-height: 1.02;
            letter-spacing: -.035em;
            background: linear-gradient(178deg, #FFFFFF 18%, #A8CFBC 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .legal {
            font-size: 13px;
            line-height: 1.5;
            color: var(--mist-2);
            text-wrap: balance;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--edge) 22%, var(--edge) 78%, transparent);
        }

        .lead {
            font-size: 14.5px;
            line-height: 1.62;
            color: var(--mist);
            opacity: .88;
            text-wrap: pretty;
        }

        .enter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            padding: 14px 24px;
            border-radius: 12px;
            background: linear-gradient(180deg, #35D291, var(--emerald));
            color: #04120B;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -.005em;
            text-decoration: none;
            box-shadow:
                inset 0 1px 0 rgb(255 255 255 / .34),
                0 12px 30px -12px rgb(31 190 123 / .7);
            transition: transform .2s cubic-bezier(.16, .84, .44, 1), box-shadow .2s ease, filter .2s ease;
        }
        .enter:hover {
            transform: translateY(-2px);
            filter: brightness(1.06);
            box-shadow:
                inset 0 1px 0 rgb(255 255 255 / .4),
                0 18px 40px -12px rgb(31 190 123 / .85);
        }
        .enter:active { transform: translateY(0); filter: brightness(.97); }
        .enter:focus-visible { outline: 2px solid var(--emerald); outline-offset: 3px; }
        .enter svg { transition: transform .2s cubic-bezier(.16, .84, .44, 1); }
        .enter:hover svg { transform: translateX(3px); }

        .aside {
            font-size: 12px;
            line-height: 1.5;
            color: var(--mist-2);
            opacity: .82;
            text-wrap: pretty;
        }

        .motto {
            font-size: 10.5px;
            font-weight: 500;
            letter-spacing: .28em;
            text-transform: uppercase;
            color: var(--mist-2);
            opacity: .62;
        }

        /* ---------- entrada escalonada ---------- */

        .cue { animation: rise .7s cubic-bezier(.16, .84, .44, 1) both; }

        @keyframes card-in {
            from { opacity: 0; transform: translateY(26px) scale(.965); filter: blur(12px); }
            to   { opacity: 1; transform: none; filter: blur(0); }
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes wake {
            from { opacity: 0; transform: scale(1.08); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes drift-a {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50%      { transform: translate3d(7vw, 5vh, 0) scale(1.14); }
        }
        @keyframes drift-b {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1.06); }
            50%      { transform: translate3d(-8vw, 7vh, 0) scale(.9); }
        }
        @keyframes drift-c {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50%      { transform: translate3d(5vw, -4vh, 0) scale(1.12); }
        }

        @media (prefers-reduced-motion: reduce) {
            .aurora, .aurora b, .card, .cue, .enter, .enter svg {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body class="home flex min-h-svh flex-col items-center justify-center gap-7 px-5 py-10 font-sans">
    <div class="aurora" aria-hidden="true"><b></b><b></b><b></b></div>
    <div class="grain" aria-hidden="true"></div>

    <main class="card relative z-10 flex flex-col items-center gap-6 text-center">
        <div class="relative">
            <span class="crest-glow" aria-hidden="true"></span>
            <img
                src="{{ asset('images/logo-web.png') }}"
                width="400"
                height="402"
                alt="{{ __('Escudo de la Institución Educativa Técnica Empresarial del Llano') }}"
                class="seal relative cue"
                style="animation-delay: .34s"
                fetchpriority="high"
            >
        </div>

        <div class="flex flex-col items-center gap-2.5">
            <h1 class="title cue" style="animation-delay: .44s">IETE del Llano</h1>

            <p class="legal cue" style="animation-delay: .52s">
                Institución Educativa Técnica Empresarial del Llano<br>
                Tauramena, Casanare
            </p>
        </div>

        <div class="divider w-full cue" style="animation-delay: .6s" aria-hidden="true"></div>

        <p class="lead cue" style="animation-delay: .66s">
            Sistema académico: registro de notas, boletines de calificaciones y certificados de estudio.
        </p>

        <div class="w-full cue" style="animation-delay: .76s">
            @auth
                <a href="{{ route('dashboard') }}" class="enter">
                    Ir al panel
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h13M13 6l6 6-6 6" />
                    </svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="enter">
                    Ingresar al sistema
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h13M13 6l6 6-6 6" />
                    </svg>
                </a>
            @endauth
        </div>

        <p class="aside cue" style="animation-delay: .84s">
            El acceso está reservado a docentes y personal administrativo. Si eres estudiante o acudiente,
            solicita tu boletín o certificado en la institución.
        </p>
    </main>

    <p class="motto relative z-10 cue" style="animation-delay: .96s">
        Responsabilidad &middot; Compromiso &middot; Respeto
    </p>
</body>
</html>
