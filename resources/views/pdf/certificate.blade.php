@php
    $badge = function ($p) {
        return match ($p) {
            'Superior', 'Alto' => ['bg' => '#ecfdf5', 'fg' => '#047857', 'br' => '#a7e3c6'],
            'Básico' => ['bg' => '#fffbeb', 'fg' => '#b45309', 'br' => '#f2d79a'],
            'Bajo' => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'br' => '#f1bcbc'],
            default => ['bg' => '#fafafa', 'fg' => '#71717a', 'br' => '#e4e4e7'],
        };
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #27272a; font-size: 11px; }
        .page { padding: 34px 40px; }
        .card { border: 1px solid #e4e4e7; border-radius: 16px; overflow: hidden; }
        .accent { width: 100%; border-collapse: collapse; }
        .accent td { height: 6px; font-size: 0; line-height: 0; }
        .inner { padding: 30px 40px; }

        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 84px; height: 84px; }
        .inst-name { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #18181b; line-height: 1.25; }
        .inst-meta { font-size: 8.5px; font-style: italic; color: #71717a; margin-top: 2px; }

        .divider { border-top: 1px solid #e4e4e7; margin: 18px 0; }
        .preamble { text-align: justify; font-size: 11px; line-height: 1.6; color: #52525b; }

        .certifican-table { width: 100%; border-collapse: collapse; margin: 22px 0; }
        .certifican-table td { vertical-align: middle; }
        .certifican-line { border-top: 1px solid #d4d4d8; font-size: 0; line-height: 0; }
        .certifican-text { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 6px; color: #18181b; white-space: nowrap; padding: 0 14px; }

        .body-text { text-align: justify; font-size: 12.5px; line-height: 2; color: #27272a; }
        .strong { font-weight: bold; text-transform: uppercase; color: #18181b; }

        table.grades { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 18px; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden; }
        table.grades th { background-color: #fafafa; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #71717a; padding: 9px 14px; text-align: left; }
        table.grades th.center { text-align: center; }
        table.grades td { padding: 8px 14px; font-size: 12px; border-top: 1px solid #f4f4f5; }
        table.grades td.center { text-align: center; }
        table.grades td.score { font-weight: bold; }
        table.grades tr.total td { border-top: 2px solid #e4e4e7; background-color: #fafafa; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; padding: 11px 14px; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid; }

        .issued { font-size: 11px; font-style: italic; color: #71717a; margin-top: 20px; }

        table.sign { width: 100%; border-collapse: collapse; margin-top: 58px; }
        table.sign td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 26px; }
        .sign-line { border-top: 1px solid #a1a1aa; padding-top: 6px; font-weight: bold; text-transform: uppercase; color: #18181b; font-size: 11.5px; }
        .sign-role { font-size: 8.5px; text-transform: uppercase; letter-spacing: 2px; color: #71717a; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <table class="accent">
                <tr>
                    @foreach (['#047857', '#078861', '#0a986c', '#0da876', '#10b981', '#3fba6e', '#6ebb5c', '#9dbd49', '#ccbe37', '#fbbf24'] as $c)
                        <td style="background-color: {{ $c }};">&nbsp;</td>
                    @endforeach
                </tr>
            </table>
            <div class="inner">
                <table class="header-table">
                    <tr>
                        <td style="width: 96px;">
                            @if ($logo)
                                <img src="{{ $logo }}" class="logo" alt="Logo">
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <div class="inst-name">{{ config('institution.name') }}</div>
                            <div class="inst-meta">{{ config('institution.resolution') }}</div>
                            <div class="inst-meta">Código DANE: {{ config('institution.dane') }} &nbsp;·&nbsp; NIT. {{ config('institution.nit') }}</div>
                            <div class="inst-meta">{{ config('institution.address') }} &nbsp;·&nbsp; Cel. {{ config('institution.phone') }} &nbsp;·&nbsp; {{ config('institution.email') }}</div>
                        </td>
                        <td style="width: 96px;"></td>
                    </tr>
                </table>

                <div class="divider"></div>

                <p class="preamble">
                    El Rector y Secretaria de la {{ config('institution.name') }}, de Tauramena Casanare, Colombia, institución educativa oficial, con reconocimiento de estudios mediante la {{ config('institution.recognition') }}
                </p>

                <table class="certifican-table">
                    <tr>
                        <td class="certifican-line">&nbsp;</td>
                        <td class="certifican-text">Certifican</td>
                        <td class="certifican-line">&nbsp;</td>
                    </tr>
                </table>

                <p class="body-text">
                    Que
                    <span class="strong">{{ $certificate['student']->last_name }}, {{ $certificate['student']->first_name }}</span>,
                    identificado(a) con Documento de Identidad Número
                    <span style="font-weight: bold;">{{ $certificate['student']->document ?? '—' }}</span>,
                    <span style="font-weight: bold;">{{ $certificate['verb'] }}</span> en esta Institución el Grado
                    <span style="font-weight: bold;">{{ $certificate['grade_label'] }}</span>
                    {{ $certificate['spans_years'] ? 'durante los años lectivos' : 'durante el año lectivo' }} <span style="font-weight: bold;">{{ $certificate['year_label'] }}</span>,
                    periodo durante el cual obtuvo los siguientes desempeños:
                </p>

                <table class="grades">
                    <thead>
                        <tr>
                            <th>Área / Asignatura</th>
                            @if ($certificate['both'])
                                <th class="center" style="width: 58px;">Sem 1</th>
                                <th class="center" style="width: 58px;">Sem 2</th>
                            @endif
                            <th class="center" style="width: 60px;">{{ $certificate['both'] ? 'Final' : 'Cal' }}</th>
                            <th class="center" style="width: 118px;">Desempeño</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($certificate['lines'] as $line)
                            <tr>
                                <td>{{ $line['name'] }}</td>
                                @if ($certificate['both'])
                                    <td class="center">{{ $line['sem1'] !== null ? number_format($line['sem1'], 2) : '—' }}</td>
                                    <td class="center">{{ $line['sem2'] !== null ? number_format($line['sem2'], 2) : '—' }}</td>
                                @endif
                                <td class="center score">{{ $line['cal'] !== null ? number_format($line['cal'], 2) : '—' }}</td>
                                <td class="center">
                                    @if ($line['performance'])
                                        @php $c = $badge($line['performance']); @endphp
                                        <span class="pill" style="background-color: {{ $c['bg'] }}; color: {{ $c['fg'] }}; border-color: {{ $c['br'] }};">{{ $line['performance'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>Promedio</td>
                            @if ($certificate['both'])
                                <td></td>
                                <td></td>
                            @endif
                            <td class="center">{{ $certificate['average'] !== null ? number_format($certificate['average'], 2) : '—' }}</td>
                            <td class="center">
                                @if ($certificate['average_performance'])
                                    @php $c = $badge($certificate['average_performance']); @endphp
                                    <span class="pill" style="background-color: {{ $c['bg'] }}; color: {{ $c['fg'] }}; border-color: {{ $c['br'] }};">{{ $certificate['average_performance'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="issued">Certificado expedido en {{ config('institution.city') }}, {{ $issuedDate }}.</p>

                <table class="sign">
                    <tr>
                        <td>
                            <div class="sign-line">{{ config('institution.rector') }}</div>
                            <div class="sign-role">Rectora</div>
                        </td>
                        <td>
                            <div class="sign-line">{{ config('institution.secretary') }}</div>
                            <div class="sign-role">Secretaria Académica</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
