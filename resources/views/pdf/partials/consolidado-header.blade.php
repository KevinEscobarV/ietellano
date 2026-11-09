{{-- Encabezado de los PDF de consolidados: logo, institución, título y el
     sello de provisional o definitivo. Usa .head, .seal y compañía del
     <style> de la plantilla que lo incluye. --}}
<table class="head">
    <tr>
        <td style="width: 54px;">
            @if ($logo)
                <img src="{{ $logo }}" class="logo" alt="Logo">
            @endif
        </td>
        <td>
            <div class="inst">{{ config('app.institution') }}</div>
            <div class="title">{{ $title }}</div>
            <div class="scope">{{ $scope }}</div>
        </td>
        <td style="width: 220px; text-align: right;">
            <span class="seal {{ $status['definitive'] ? 'def' : 'prov' }}">{{ mb_strtoupper($status['label']) }}</span>
            <div class="seal-detail">{{ $status['detail'] }}</div>
        </td>
    </tr>
</table>
