@php
    $num = fn ($v) => $v !== null ? number_format($v, 2) : '—';
    $isFail = fn ($v) => $v !== null && $v < $passingScore;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 38px; }
        body { margin: 0; padding: 0; }
        div, table, th, td, span { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #27272a; font-size: 9px; }
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

        table.kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 10px 0 8px; }
        table.kpis td { border: 1px solid #e4e4e7; border-radius: 8px; padding: 6px 9px; vertical-align: top; }
        .kpi-label { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; color: #71717a; }
        .kpi-value { font-size: 15px; font-weight: bold; color: #18181b; margin-top: 2px; }
        .kpi-value.fail { color: #b91c1c; }
        .kpi-note { font-size: 7.5px; color: #71717a; margin-top: 1px; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid thead { display: table-header-group; }
        table.grid th { font-size: 7px; font-weight: bold; color: #52525b; background-color: #f4f4f5; border: 1px solid #d4d4d8; padding: 4px 3px; text-align: center; vertical-align: middle; }
        table.grid th.name, table.grid td.name { text-align: left; }
        table.grid td { font-size: 8px; border: 1px solid #e4e4e7; padding: 3px; text-align: center; }
        table.grid td.name { white-space: nowrap; }
        table.grid td.muted { color: #a1a1aa; }
        table.grid td.fail { background-color: #fef2f2; color: #b91c1c; font-weight: bold; }
        table.grid td.pending { color: #a1a1aa; font-style: italic; }
        table.grid td.strong { font-weight: bold; }
        table.grid tr { page-break-inside: avoid; }
        table.grid tr.stat td { background-color: #fafafa; font-weight: bold; }
        .legend { font-size: 7px; color: #a1a1aa; margin-top: 6px; }
    </style>
</head>
<body>
    {{-- Con todo el semestre, primero un renglón por ciclo. --}}
    @if ($termLabel)
        @php $allClosed = collect($consolidados)->every(fn ($c) => $c['status']['definitive']); @endphp

        @include('pdf.partials.consolidado-header', [
            'title' => ($consolidados[0]['title'] ?? 'Definitivas').' del semestre',
            'scope' => $termLabel,
            'status' => [
                'definitive' => $allClosed,
                'label' => $allClosed ? 'Definitivo' : 'Provisional',
                'detail' => $allClosed ? 'Todos los ciclos están cerrados' : 'Hay ciclos con módulos abiertos',
            ],
        ])

        <table class="grid" style="margin-top: 14px;">
            <thead>
                <tr>
                    <th class="name">Ciclo</th>
                    <th>Se lee</th>
                    <th>Estudiantes</th>
                    <th>Promedio</th>
                    <th>Desempeño</th>
                    <th>Aprueban todo</th>
                    <th>Pierden 1</th>
                    <th>Pierden 2</th>
                    <th>Pierden 3 o más</th>
                    <th>Notas que faltan</th>
                    <th class="name">Materia con más pérdidas</th>
                    <th>No siguieron del 1.er semestre</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($consolidados as $c)
                    @php $s = $c['summary']; $hardest = $s['hardest'][0] ?? null; @endphp
                    <tr>
                        <td class="name strong">{{ $c['cycle']->name }}</td>
                        <td>{{ $c['year'] ? "Año ({$c['yearParts']['previous']} + {$c['yearParts']['current']})" : 'Semestre' }}</td>
                        <td>{{ $s['students'] }}</td>
                        <td class="{{ $isFail($s['average']) ? 'fail' : 'strong' }}">{{ $num($s['average']) }}</td>
                        <td>{{ $s['performance'] ?? '—' }}</td>
                        <td>{{ $s['all_passed'] }}</td>
                        <td class="{{ $s['failing'][1] > 0 ? 'fail' : '' }}">{{ $s['failing'][1] }}</td>
                        <td class="{{ $s['failing'][2] > 0 ? 'fail' : '' }}">{{ $s['failing'][2] }}</td>
                        <td class="{{ $s['failing'][3] > 0 ? 'fail' : '' }}">{{ $s['failing'][3] }}</td>
                        <td>{{ $s['pending_cells'] }}</td>
                        <td class="name">{{ $hardest ? "{$hardest['name']} ({$hardest['failed']} de {$hardest['graded']})" : '—' }}</td>
                        <td>{{ $c['year'] ? $s['dropped'] : '' }}</td>
                        <td>{{ $c['status']['label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legend">
            En los ciclos de un año (3 y 4), el segundo semestre se lee con el año completo: la nota de cada materia es el promedio de los dos semestres.
        </div>
    @endif

    @foreach ($consolidados as $c)
        @php
            $s = $c['summary'];
            $showPending = $s['pending_cells'] > 0 || $s['without_previous'] > 0;
        @endphp

        <div class="{{ $termLabel || ! $loop->first ? 'page-break' : '' }}">
            @include('pdf.partials.consolidado-header', ['title' => $c['title'], 'scope' => $c['scope'], 'status' => $c['status']])

            <table class="kpis">
                <tr>
                    <td style="width: 15%;">
                        <div class="kpi-label">Estudiantes</div>
                        <div class="kpi-value">{{ $s['students'] }}</div>
                        @if ($s['without_previous'] > 0)
                            <div class="kpi-note">{{ $s['without_previous'] }} sin {{ $c['yearParts']['previous'] }}</div>
                        @endif
                        @if ($s['dropped'] > 0)
                            <div class="kpi-note">{{ $s['dropped'] }} de {{ $c['yearParts']['previous'] }} no siguieron</div>
                        @endif
                        @if ($s['without_grades'] > 0)
                            <div class="kpi-note">{{ $s['without_grades'] }} sin notas</div>
                        @endif
                    </td>
                    <td style="width: 17%;">
                        <div class="kpi-label">Promedio del ciclo</div>
                        <div class="kpi-value {{ $isFail($s['average']) ? 'fail' : '' }}">{{ $num($s['average']) }}</div>
                        <div class="kpi-note">{{ $s['performance'] ?? 'Sin notas' }}</div>
                    </td>
                    <td style="width: 17%;">
                        <div class="kpi-label">Aprueban todo</div>
                        <div class="kpi-value">{{ $s['all_passed'] }}</div>
                        <div class="kpi-note">de {{ $s['graded'] }} {{ $c['year'] ? 'con los dos semestres' : 'con notas' }}</div>
                    </td>
                    <td style="width: 19%;">
                        <div class="kpi-label">Pierden alguna materia</div>
                        <div class="kpi-value {{ $s['failing_total'] > 0 ? 'fail' : '' }}">{{ $s['failing_total'] }}</div>
                        <div class="kpi-note">1: {{ $s['failing'][1] }} · 2: {{ $s['failing'][2] }} · 3 o más: {{ $s['failing'][3] }}</div>
                    </td>
                    <td>
                        <div class="kpi-label">Donde más se pierde</div>
                        @forelse ($s['hardest'] as $h)
                            <div class="kpi-note" style="color: #27272a;">{{ $h['name'] }}: {{ $h['failed'] }} de {{ $h['graded'] }}</div>
                        @empty
                            <div class="kpi-note">Nadie pierde ninguna materia</div>
                        @endforelse
                    </td>
                </tr>
            </table>

            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 16px;">N°</th>
                        <th class="name">Estudiante</th>
                        @if ($c['showGroup'])
                            <th>Grupo</th>
                        @endif
                        @foreach ($c['columns'] as $column)
                            <th>{{ $column['name'] }}</th>
                        @endforeach
                        <th>Prom.</th>
                        <th>Puesto</th>
                        <th>Pierde</th>
                        @if ($showPending)
                            <th>Faltan</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($c['rows'] as $row)
                        <tr>
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td class="name">
                                {{ $row['name'] }}
                                @if ($c['year'] && ! $row['took_previous'])
                                    <span style="color: #b45309; font-size: 7px;">· sin {{ $c['yearParts']['previous'] }}</span>
                                @endif
                            </td>
                            @if ($c['showGroup'])
                                <td style="white-space: nowrap;">{{ $row['group'] ?? '—' }}</td>
                            @endif
                            @foreach ($c['columns'] as $column)
                                @php $cell = $row['cells'][$column['name']] ?? null; @endphp
                                @if ($cell === null)
                                    <td></td>
                                @else
                                    <td class="{{ $cell['failed'] ? 'fail' : '' }} {{ $cell['complete'] ? '' : 'pending' }}">{{ $num($cell['score']) }}</td>
                                @endif
                            @endforeach
                            <td class="{{ $isFail($row['average']) ? 'fail' : 'strong' }}">{{ $num($row['average']) }}</td>
                            <td>{{ $row['rank'] ?? '—' }}</td>
                            <td class="{{ $row['failed'] > 0 ? 'fail' : '' }}">{{ $row['failed'] }}</td>
                            @if ($showPending)
                                <td class="muted">{{ $row['pending'] ?: '' }}</td>
                            @endif
                        </tr>
                    @endforeach

                    @foreach (['average' => 'Promedio', 'passed' => 'Aprueban', 'failed' => 'Pierden'] as $key => $label)
                        <tr class="stat">
                            <td></td>
                            <td class="name">{{ $label }}</td>
                            @if ($c['showGroup'])
                                <td></td>
                            @endif
                            @foreach ($c['columns'] as $column)
                                @php $value = $c['stats'][$column['name']][$key]; @endphp
                                @if ($key === 'average')
                                    <td class="{{ $isFail($value) ? 'fail' : '' }}">{{ $num($value) }}</td>
                                @else
                                    <td class="{{ $key === 'failed' && $value > 0 ? 'fail' : '' }}">{{ $value }}</td>
                                @endif
                            @endforeach
                            <td>{{ $key === 'average' ? $num($s['average']) : '' }}</td>
                            <td></td>
                            <td></td>
                            @if ($showPending)
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="legend">
                En rojo, las notas por debajo de {{ number_format($passingScore, 1) }}.
                @if ($showPending)
                    En gris y cursiva, las que todavía no están completas porque falta la nota de algún módulo{{ $c['year'] ? ' o de algún semestre' : '' }}.
                @endif
                @if ($c['year'])
                    Nota del año: promedio de la definitiva de {{ $c['yearParts']['previous'] }} y la de {{ $c['yearParts']['current'] }}.
                    Quienes no cursaron {{ $c['yearParts']['previous'] }} van sin puesto y fuera de las cuentas del ciclo.
                @endif
                Generado el {{ now()->translatedFormat('j \d\e F \d\e Y, g:i a') }}
            </div>

            @if ($c['dropped'] !== [])
                <div style="page-break-inside: avoid; margin-top: 12px;">
                    <div class="kpi-label" style="margin-bottom: 4px;">
                        Cursaron {{ $c['yearParts']['previous'] }} y no siguieron en {{ $c['yearParts']['current'] }} ({{ count($c['dropped']) }})
                    </div>
                    <table class="grid" style="width: auto;">
                        <thead>
                            <tr>
                                <th class="name">Estudiante</th>
                                <th>Documento</th>
                                <th>Grupo en {{ $c['yearParts']['previous'] }}</th>
                                <th>Promedio en {{ $c['yearParts']['previous'] }}</th>
                                <th>Perdió</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($c['dropped'] as $student)
                                <tr>
                                    <td class="name">{{ $student['name'] }}</td>
                                    <td class="muted">{{ $student['document'] ?? '—' }}</td>
                                    <td>{{ $student['group'] ?? '—' }}</td>
                                    <td class="{{ $isFail($student['average']) ? 'fail' : 'strong' }}">{{ $num($student['average']) }}</td>
                                    <td class="{{ $student['failed'] > 0 ? 'fail' : '' }}">{{ $student['failed'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
