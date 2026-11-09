@props(['certificate', 'issuedDate'])

{{-- El certificado de calificaciones tal como se imprime. La página lo deja
     solo en la hoja al imprimir (ver el @media print de la vista). --}}

@php
    $badge = fn ($p) => match ($p) {
        'Superior', 'Alto' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'Básico' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
        'Bajo' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300',
        default => 'bg-zinc-50 text-zinc-500 ring-zinc-500/20',
    };
@endphp

<div id="certificate" class="mx-auto max-w-3xl overflow-hidden rounded-2xl bg-white text-zinc-800 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700 print:max-w-none print:text-black print:ring-0">
    {{-- Accent bar --}}
    <div class="h-1.5 bg-linear-to-r from-emerald-700 via-emerald-500 to-amber-400"></div>

    <div class="px-12 py-10 print:px-8 print:py-6">
        {{-- Institution header --}}
        <div class="flex items-center gap-5">
            <img src="{{ asset('images/logo.png') }}" alt="{{ __('Logo') }}" class="size-24 shrink-0 object-contain">
            <div class="flex-1 text-center">
                <h1 class="text-xl font-bold uppercase leading-tight tracking-wide text-zinc-900 dark:text-white print:text-black">{{ config('institution.name') }}</h1>
                <p class="mt-1.5 text-xs italic text-zinc-500 dark:text-zinc-400">{{ config('institution.resolution') }}</p>
                <p class="text-xs italic text-zinc-500 dark:text-zinc-400">{{ __('Código DANE') }}: {{ config('institution.dane') }} &nbsp;·&nbsp; NIT. {{ config('institution.nit') }}</p>
                <p class="mt-1 text-[11px] italic text-zinc-400">{{ config('institution.address') }} &nbsp;·&nbsp; {{ __('Cel.') }} {{ config('institution.phone') }} &nbsp;·&nbsp; {{ config('institution.email') }}</p>
            </div>
        </div>

        <div class="my-6 h-px bg-linear-to-r from-transparent via-zinc-300 to-transparent dark:via-zinc-600"></div>

        {{-- Preamble --}}
        <p class="text-justify text-[13px] leading-relaxed text-zinc-600 dark:text-zinc-300">
            {{ __('El Rector y Secretaria de la :name, de Tauramena Casanare, Colombia, institución educativa oficial, con reconocimiento de estudios mediante la :recognition', ['name' => config('institution.name'), 'recognition' => config('institution.recognition')]) }}
        </p>

        {{-- Certifican --}}
        <div class="my-7 flex items-center justify-center gap-4">
            <span class="h-px w-16 bg-zinc-300 dark:bg-zinc-600"></span>
            <span class="text-lg font-bold uppercase tracking-[0.3em] text-zinc-900 dark:text-white print:text-black">{{ __('Certifican') }}</span>
            <span class="h-px w-16 bg-zinc-300 dark:bg-zinc-600"></span>
        </div>

        <p class="text-justify text-[15px] leading-loose">
            {{ __('Que') }}
            <span class="font-bold uppercase tracking-wide text-zinc-900 dark:text-white print:text-black">{{ $certificate['student']->last_name }}, {{ $certificate['student']->first_name }}</span>,
            {{ __('identificado(a) con Documento de Identidad Número') }}
            <span class="font-semibold">{{ $certificate['student']->document ?? '—' }}</span>,
            <span class="font-semibold">{{ $certificate['verb'] }}</span> {{ __('en esta Institución el Grado') }}
            <span class="font-bold">{{ $certificate['grade_label'] }}</span>
            {{ $certificate['spans_years'] ? __('durante los años lectivos') : __('durante el año lectivo') }} <span class="font-bold">{{ $certificate['year_label'] }}</span>,
            {{ __('periodo durante el cual obtuvo los siguientes desempeños:') }}
        </p>

        {{-- Grades table --}}
        <div class="mt-6 overflow-hidden rounded-xl ring-1 ring-zinc-200 dark:ring-zinc-700 print:ring-zinc-300">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-50 text-[11px] uppercase tracking-wider text-zinc-500 dark:bg-zinc-800 print:bg-zinc-100">
                        <th class="px-4 py-2.5 text-left font-semibold">{{ __('Área / Asignatura') }}</th>
                        @if ($certificate['both'])
                            <th class="w-20 px-4 py-2.5 text-center font-semibold">{{ __('Sem 1') }}</th>
                            <th class="w-20 px-4 py-2.5 text-center font-semibold">{{ __('Sem 2') }}</th>
                        @endif
                        <th class="w-20 px-4 py-2.5 text-center font-semibold">{{ $certificate['both'] ? __('Final') : __('Cal') }}</th>
                        <th class="w-40 px-4 py-2.5 text-center font-semibold">{{ __('Desempeño') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($certificate['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2.5">{{ $line['name'] }}</td>
                            @if ($certificate['both'])
                                <td class="px-4 py-2.5 text-center tabular-nums text-zinc-500">{{ $line['sem1'] !== null ? number_format($line['sem1'], 2) : '—' }}</td>
                                <td class="px-4 py-2.5 text-center tabular-nums text-zinc-500">{{ $line['sem2'] !== null ? number_format($line['sem2'], 2) : '—' }}</td>
                            @endif
                            <td class="px-4 py-2.5 text-center font-semibold tabular-nums">{{ $line['cal'] !== null ? number_format($line['cal'], 2) : '—' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if ($line['performance'])
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset {{ $badge($line['performance']) }}">{{ $line['performance'] }}</span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-zinc-200 bg-zinc-50/60 font-bold dark:border-zinc-700 dark:bg-zinc-800/40 print:bg-zinc-100">
                        <td class="px-4 py-3 uppercase tracking-wide">{{ __('Promedio') }}</td>
                        @if ($certificate['both'])
                            <td></td>
                            <td></td>
                        @endif
                        <td class="px-4 py-3 text-center tabular-nums">{{ $certificate['average'] !== null ? number_format($certificate['average'], 2) : '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($certificate['average_performance'])
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset {{ $badge($certificate['average_performance']) }}">{{ $certificate['average_performance'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-7 text-[13px] italic text-zinc-500 dark:text-zinc-400">
            {{ __('Certificado expedido en :city, :date.', ['city' => config('institution.city'), 'date' => $issuedDate]) }}
        </p>

        {{-- Signatures --}}
        <div class="mt-16 grid grid-cols-2 gap-12 text-center text-sm">
            <div>
                <div class="mx-auto w-56 border-t border-zinc-400 pt-2 font-bold uppercase tracking-wide text-zinc-900 dark:text-white print:text-black">{{ config('institution.rector') }}</div>
                <div class="text-xs uppercase tracking-widest text-zinc-500">{{ __('Rectora') }}</div>
            </div>
            <div>
                <div class="mx-auto w-56 border-t border-zinc-400 pt-2 font-bold uppercase tracking-wide text-zinc-900 dark:text-white print:text-black">{{ config('institution.secretary') }}</div>
                <div class="text-xs uppercase tracking-widest text-zinc-500">{{ __('Secretaria Académica') }}</div>
            </div>
        </div>
    </div>
</div>
