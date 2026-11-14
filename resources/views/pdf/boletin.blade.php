@php
    $badge = function ($p) {
        return match ($p) {
            'Superior', 'Alto' => ['bg' => '#ecfdf5', 'fg' => '#047857', 'br' => '#a7e3c6'],
            'Básico' => ['bg' => '#fffbeb', 'fg' => '#b45309', 'br' => '#f2d79a'],
            'Bajo' => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'br' => '#f1bcbc'],
            default => ['bg' => '#fafafa', 'fg' => '#71717a', 'br' => '#e4e4e7'],
        };
    };
    $num = fn ($v) => $v !== null ? number_format($v, 2) : '—';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #27272a; font-size: 11px; }
        .page { padding: 26px 36px; }
        .card { border: 1px solid #e4e4e7; border-radius: 16px; overflow: hidden; }
        .accent { width: 100%; border-collapse: collapse; }
        .accent td { height: 6px; font-size: 0; line-height: 0; }
        .inner { padding: 20px 30px 22px; }

        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 64px; height: 64px; }
        .inst-name { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #18181b; line-height: 1.25; }
        .inst-sub { font-size: 9px; color: #71717a; margin-top: 2px; }
        .doc-title { font-size: 12px; font-weight: bold; letter-spacing: 1px; color: #18181b; margin-top: 6px; }
        .cycle-meta { font-size: 9.5px; color: #71717a; margin-top: 1px; }

        .meta { width: 100%; border-collapse: collapse; margin-top: 14px; background-color: #fafafa; border: 1px solid #f0f0f1; border-radius: 10px; }
        .meta td { padding: 7px 12px; font-size: 10.5px; width: 50%; }
        .meta .lbl { color: #71717a; }
        .meta .val { font-weight: bold; color: #18181b; }

        table.grades { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.grades thead th { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #71717a; padding: 7px 8px; border-top: 1px solid #d4d4d8; border-bottom: 1px solid #d4d4d8; text-align: center; }
        table.grades thead th.area { text-align: left; }
        table.grades td { padding: 5px 8px; font-size: 11.5px; border-bottom: 1px solid #f4f4f5; vertical-align: top; }
        table.grades td.c { text-align: center; }
        .line-name { font-weight: bold; color: #18181b; }
        .line-teacher { font-size: 9px; color: #a1a1aa; margin-top: 1px; }
        table.grades tr.area td { border-bottom: 0; padding-bottom: 2px; }
        table.grades tr.component td { font-size: 10.5px; color: #52525b; padding-top: 2px; padding-bottom: 2px; border-bottom: 0; }
        table.grades tr.component.last td { border-bottom: 1px solid #f4f4f5; padding-bottom: 5px; }
        table.grades tr.component td.sub { padding-left: 16px; }
        .sub-inner { border-left: 2px solid #e4e4e7; padding-left: 8px; }
        .sub-name { color: #3f3f46; }
        table.grades tr.component td.final-sub { font-weight: bold; color: #3f3f46; }
        table.grades td.fail, table.grades tr.component td.fail { color: #b91c1c; }
        .final { font-weight: bold; }
        .final.fail { color: #b91c1c; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 10px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid; }
        tr.total td { border-top: 2px solid #d4d4d8; border-bottom: 0; font-weight: bold; padding-top: 8px; font-size: 11.5px; }

        .legend { font-size: 9px; color: #a1a1aa; margin-top: 10px; }

        table.sign { width: 100%; border-collapse: collapse; margin-top: 40px; }
        table.sign td { width: 50%; text-align: center; padding: 0 24px; }
        .sign-line { border-top: 1px solid #a1a1aa; padding-top: 6px; font-size: 10.5px; color: #52525b; }
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
                        <td style="width: 84px;">
                            @if ($logo)
                                <img src="{{ $logo }}" class="logo" alt="Logo">
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <div class="inst-name">{{ config('app.institution') }}</div>
                            <div class="inst-sub">Educación de adultos · Validación de bachillerato</div>
                            <div class="doc-title">BOLETÍN DE CALIFICACIONES</div>
                            <div class="cycle-meta">{{ $boletin['cycle_label'] }} · Año {{ $boletin['cycle']->year }} · Semestre {{ $boletin['cycle']->semester }}@if ($boletin['period']) · Módulo {{ $boletin['period'] }}@endif</div>
                        </td>
                        <td style="width: 84px;"></td>
                    </tr>
                </table>

                <table class="meta">
                    <tr>
                        <td><span class="lbl">Estudiante:</span> <span class="val">{{ $boletin['student']->fullName() }}</span></td>
                        <td><span class="lbl">Documento:</span> <span class="val">{{ $boletin['student']->document ?? '—' }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="lbl">Grupo:</span> <span class="val">{{ $boletin['group'] ?? '—' }}</span></td>
                        @if ($boletin['both'])
                            <td><span class="lbl">Semestres:</span> <span class="val">{{ $boletin['previous']->year }} – {{ $boletin['cycle']->year }}</span></td>
                        @elseif ($boletin['period'])
                            <td><span class="lbl">Módulo:</span> <span class="val">{{ $boletin['period'] }}</span></td>
                        @else
                            <td><span class="lbl">Módulos:</span> <span class="val">1 y 2</span></td>
                        @endif
                    </tr>
                </table>

                @php
                    $details = $boletin['detail_columns'];
                    $fails = fn ($v) => $v !== null && $v < $passingScore;
                    $absences = fn (array $l) => ($l['held'] ?? 0) > 0 ? ($l['absences'] ?? 0) : '—';
                @endphp

                {{-- Un área con su promedio y, debajo, una fila por materia con
                     sus propias notas en las mismas columnas. --}}
                <table class="grades">
                    <thead>
                        <tr>
                            <th class="area">Área / Materia</th>
                            @foreach ($details as $detail)
                                <th style="width: 52px;">{{ $detail['label'] }}</th>
                            @endforeach
                            <th style="width: 56px;">{{ $boletin['final_label'] }}</th>
                            <th style="width: 46px;">Fallas</th>
                            <th style="width: 110px;">Desempeño</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($boletin['lines'] as $line)
                            @php $isArea = $line['type'] === 'area'; @endphp
                            <tr class="{{ $isArea ? 'area' : '' }}">
                                <td>
                                    <div class="line-name">{{ $line['name'] }}</div>
                                    @if ($isArea)
                                        <div class="line-teacher">Promedio de {{ implode(' y ', array_column($line['components'], 'name')) }}</div>
                                    @elseif ($line['teacher'] ?? null)
                                        <div class="line-teacher">{{ $line['teacher'] }}</div>
                                    @endif
                                </td>
                                @foreach ($details as $detail)
                                    <td class="c">{{ $num($line[$detail['key']] ?? null) }}</td>
                                @endforeach
                                <td class="c final {{ $fails($line['final']) ? 'fail' : '' }}">{{ $num($line['final']) }}</td>
                                <td class="c {{ ! $isArea && ($line['lost_by_absence'] ?? false) ? 'fail' : '' }}">
                                    {{ $isArea ? '' : $absences($line) }}
                                </td>
                                <td class="c">
                                    @if ($line['performance'])
                                        @php $b = $badge($line['performance']); @endphp
                                        <span class="pill" style="background-color: {{ $b['bg'] }}; color: {{ $b['fg'] }}; border-color: {{ $b['br'] }};">{{ $line['performance'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>

                            @foreach ($line['components'] as $component)
                                <tr class="component {{ $loop->last ? 'last' : '' }}">
                                    <td class="sub">
                                        <div class="sub-inner">
                                            <div class="sub-name">{{ $component['name'] }}</div>
                                            @if ($component['teacher'] ?? null)
                                                <div class="line-teacher">{{ $component['teacher'] }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    @foreach ($details as $detail)
                                        <td class="c">{{ $num($component[$detail['key']] ?? null) }}</td>
                                    @endforeach
                                    <td class="c final-sub {{ $fails($component['final']) ? 'fail' : '' }}">{{ $num($component['final']) }}</td>
                                    <td class="c {{ ($component['lost_by_absence'] ?? false) ? 'fail' : '' }}">{{ $absences($component) }}</td>
                                    <td></td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr class="total">
                            <td>Promedio general</td>
                            @foreach ($details as $detail)
                                <td></td>
                            @endforeach
                            <td class="c">{{ $num($boletin['overall']) }}</td>
                            <td></td>
                            <td class="c">
                                @if ($boletin['overall_performance'])
                                    @php $b = $badge($boletin['overall_performance']); @endphp
                                    <span class="pill" style="background-color: {{ $b['bg'] }}; color: {{ $b['fg'] }}; border-color: {{ $b['br'] }};">{{ $boletin['overall_performance'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="legend">
                    Escala: Superior (4.6–5.0) · Alto (4.0–4.5) · Básico (3.0–3.9) · Bajo (0.0–2.9). Nota mínima de aprobación: 3.0.
                    @if ($boletin['has_areas'])
                        La nota de un área es el promedio de las notas de sus materias; una materia por debajo de 3.0 sale en rojo aunque el área apruebe.
                    @endif
                </p>

                <table class="sign">
                    <tr>
                        <td><div class="sign-line">Director de grupo</div></td>
                        <td><div class="sign-line">Rector</div></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
