@php
    $num = fn ($v) => $v !== null ? number_format($v, 2) : '—';
    $isFail = fn ($v) => $v !== null && $v < $passingScore;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 36px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #27272a; font-size: 9.5px; }
        .page-break { page-break-before: always; }

        table.head { width: 100%; border-collapse: collapse; }
        table.head td { vertical-align: middle; }
        .logo { width: 46px; height: 46px; }
        .inst { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; color: #18181b; }
        .title { font-size: 10.5px; font-weight: bold; color: #18181b; margin-top: 2px; }
        .scope { font-size: 8.5px; color: #71717a; margin-top: 1px; }
        .seal { display: inline-block; padding: 3px 10px; border-radius: 8px; font-size: 8px; font-weight: bold; letter-spacing: 0.6px; border: 1px solid; }
        .seal.def { background-color: #ecfdf5; color: #047857; border-color: #a7e3c6; }
        .seal.prov { background-color: #fffbeb; color: #92400e; border-color: #f2d79a; }
        .seal-detail { font-size: 7.5px; color: #71717a; margin-top: 3px; }

        .section-title { font-size: 12px; font-weight: bold; color: #18181b; margin-top: 16px; }
        .section-meta { font-size: 8.5px; color: #71717a; margin: 2px 0 8px; }
        .section-meta b { color: #27272a; }
        .section-meta .fail { color: #b91c1c; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid thead { display: table-header-group; }
        table.grid th { font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #52525b; background-color: #f4f4f5; border: 1px solid #d4d4d8; padding: 5px 4px; text-align: center; }
        table.grid th.name, table.grid td.name { text-align: left; }
        table.grid td { font-size: 9px; border: 1px solid #e4e4e7; padding: 4px; text-align: center; }
        table.grid td.muted { color: #a1a1aa; }
        table.grid td.fail { background-color: #fef2f2; color: #b91c1c; font-weight: bold; }
        table.grid td.pending { color: #a1a1aa; font-style: italic; }
        table.grid td.strong { font-weight: bold; }
        table.grid tr { page-break-inside: avoid; }
        table.grid tr.stat td { background-color: #fafafa; font-weight: bold; }
        .legend { font-size: 7.5px; color: #a1a1aa; margin-top: 6px; }
    </style>
</head>
<body>
    @foreach ($data['sections'] as $section)
        @php
            $stats = $section['stats'];
            $parts = $data['yearParts'];
        @endphp

        <div class="{{ $loop->first ? '' : 'page-break' }}">
            @include('pdf.partials.consolidado-header', [
                'title' => 'Consolidado de '.$data['teacher']->name,
                'scope' => $data['scope'],
                'status' => $data['status'],
            ])

            <div class="section-title">{{ $section['subject'] }}{{ $section['group'] ? ' · '.$section['group'] : '' }}</div>
            <div class="section-meta">
                <b>{{ $stats['students'] }}</b> estudiantes ·
                promedio <b>{{ $num($stats['final']) }}</b> ·
                aprueban <b>{{ $stats['passed'] }}</b> ·
                pierden <b class="{{ $stats['failed'] > 0 ? 'fail' : '' }}">{{ $stats['failed'] }}</b>
                @if ($stats['pending'] > 0)
                    · con nota incompleta <b>{{ $stats['pending'] }}</b>
                @endif
                @if ($section['year'])
                    <br>En el año: promedio <b>{{ $num($stats['year']) }}</b> ·
                    pierden <b class="{{ $stats['year_failed'] > 0 ? 'fail' : '' }}">{{ $stats['year_failed'] }}</b>
                    @if ($stats['without_previous'] > 0)
                        · sin {{ $parts['previous'] }} <b>{{ $stats['without_previous'] }}</b>
                    @endif
                @endif
            </div>

            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 20px;">N°</th>
                        <th class="name">Estudiante</th>
                        <th style="width: 72px;">Documento</th>
                        @if ($section['year'])
                            <th style="width: 50px;">Def. {{ $parts['previous'] }}</th>
                        @endif
                        <th style="width: 46px;">Módulo 1</th>
                        <th style="width: 46px;">Módulo 2</th>
                        @if ($section['year'])
                            <th style="width: 50px;">Def. {{ $parts['current'] }}</th>
                            <th style="width: 46px;">Año</th>
                            <th style="width: 60px;">Desempeño del año</th>
                        @else
                            <th style="width: 56px;">Definitiva</th>
                            <th style="width: 64px;">Desempeño</th>
                        @endif
                        <th style="width: 36px;">Fallas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td class="name">{{ $row['name'] }}</td>
                            <td class="muted">{{ $row['document'] ?? '—' }}</td>
                            @if ($section['year'])
                                @if ($row['took_previous'])
                                    <td class="{{ $isFail($row['previous']) ? 'fail' : '' }}">{{ $num($row['previous']) }}</td>
                                @else
                                    <td class="pending">No cursó</td>
                                @endif
                            @endif
                            <td class="{{ $isFail($row['m1']) ? 'fail' : '' }}">{{ $num($row['m1']) }}</td>
                            <td class="{{ $isFail($row['m2']) ? 'fail' : '' }}">{{ $num($row['m2']) }}</td>
                            <td class="strong {{ $row['failed'] ? 'fail' : '' }} {{ $row['complete'] ? '' : 'pending' }}">{{ $num($row['final']) }}</td>
                            @if ($section['year'])
                                @if ($row['took_previous'])
                                    <td class="strong {{ $row['year_failed'] ? 'fail' : '' }} {{ $row['year_complete'] ? '' : 'pending' }}">{{ $num($row['year']) }}</td>
                                @else
                                    <td></td>
                                @endif
                            @endif
                            <td>{{ $row['performance'] ?? '—' }}</td>
                            <td class="{{ $row['lost_by_absence'] ? 'fail' : '' }}">{{ $row['absences'] }}</td>
                        </tr>
                    @endforeach

                    <tr class="stat">
                        <td></td>
                        <td class="name">Promedio</td>
                        <td></td>
                        @if ($section['year'])
                            <td>{{ $num($stats['previous']) }}</td>
                        @endif
                        <td>{{ $num($stats['m1']) }}</td>
                        <td>{{ $num($stats['m2']) }}</td>
                        <td>{{ $num($stats['final']) }}</td>
                        @if ($section['year'])
                            <td>{{ $num($stats['year']) }}</td>
                        @endif
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="legend">
                En rojo, las notas por debajo de {{ number_format($passingScore, 1) }} y las fallas por encima del tope.
                En gris y cursiva, la nota incompleta porque falta la de algún módulo.
                @if ($section['year'])
                    Año: promedio de la definitiva de {{ $parts['previous'] }} y la de {{ $parts['current'] }}.
                @endif
                Generado el {{ now()->translatedFormat('j \d\e F \d\e Y, g:i a') }}
            </div>
        </div>
    @endforeach
</body>
</html>
