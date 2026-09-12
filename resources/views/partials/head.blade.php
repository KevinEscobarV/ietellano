<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- El script de apariencia sigue la preferencia del dispositivo y le pone (o
     le quita) la clase `dark` a la página. Las pantallas que pintan un solo
     mundo de colores —la consulta pública, que además se imprime— lo omiten
     con :appearance="false". --}}
@if ($appearance ?? true)
    @fluxAppearance
@endif
