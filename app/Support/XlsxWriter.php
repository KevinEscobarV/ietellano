<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Escribe un .xlsx sin dependencias, con ZipArchive y el XML a mano, como
 * XlsxReader lo lee. Alcanza para tablas con un juego fijo de estilos: los
 * números quedan como números (se ordenan y filtran en Excel) y el texto va
 * en línea, sin tabla de cadenas compartidas.
 */
class XlsxWriter
{
    /**
     * Nombre del estilo => posición en cellXfs de styles.xml.
     */
    public const STYLES = [
        'default' => 0,
        'title' => 1,
        'subtitle' => 2,
        'muted' => 3,
        'header' => 4,
        'text' => 5,
        'center' => 6,
        'score' => 7,
        'score-fail' => 8,
        'score-pending' => 9,
        'score-pending-fail' => 10,
        'integer' => 11,
        'integer-fail' => 12,
        'bold' => 13,
        'bold-score' => 14,
        'seal-provisional' => 15,
        'seal-definitive' => 16,
    ];

    private const MAX_SHEET_NAME = 31;

    /**
     * @var list<array{name: string, xml: string, filter: ?string}>
     */
    private array $sheets = [];

    /**
     * Una celda con estilo. Las demás pueden ir como el valor solo.
     *
     * @return array{value: string|int|float|null, style: string}
     */
    public static function cell(string|int|float|null $value, string $style = 'default'): array
    {
        return ['value' => $value, 'style' => $style];
    }

    /**
     * La letra de la columna a partir de su posición (0 = A, 26 = AA).
     */
    public static function column(int $index): string
    {
        $letters = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letters = chr(65 + ($index - 1) % 26).$letters;
        }

        return $letters;
    }

    /**
     * En las opciones, widths va por posición de columna (desde 0), heights
     * por número de fila (desde 1), freeze son las [columnas, filas] fijas y
     * filter un rango como "A6:P30".
     *
     * @param  list<list<string|int|float|null|array{value: string|int|float|null, style: string}>>  $rows
     * @param  array{widths?: array<int, float|int>, heights?: array<int, float|int>, freeze?: array{0: int, 1: int}, filter?: string, merge?: list<string>, landscape?: bool}  $options
     */
    public function addSheet(string $name, array $rows, array $options = []): static
    {
        $this->sheets[] = [
            'name' => $this->uniqueName($name),
            'xml' => $this->sheetXml($rows, $options, $this->sheets === []),
            'filter' => $options['filter'] ?? null,
        ];

        return $this;
    }

    public function save(string $path): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('El libro no tiene hojas.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el archivo: {$path}");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $sheet['xml']);
        }

        $zip->close();
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  array<string, mixed>  $options
     */
    private function sheetXml(array $rows, array $options, bool $first): string
    {
        $landscape = $options['landscape'] ?? false;
        $heights = $options['heights'] ?? [];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        if ($landscape) {
            $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        }

        $xml .= '<sheetViews><sheetView workbookViewId="0"'.($first ? ' tabSelected="1"' : '').'>'
            .$this->pane($options['freeze'] ?? [0, 0])
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>';

        if (($options['widths'] ?? []) !== []) {
            $xml .= '<cols>';

            foreach ($options['widths'] as $column => $width) {
                $position = $column + 1;
                $xml .= '<col min="'.$position.'" max="'.$position.'" width="'.$width.'" customWidth="1"/>';
            }

            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach (array_values($rows) as $index => $cells) {
            $number = $index + 1;
            $content = '';

            foreach (array_values($cells) as $column => $cell) {
                $content .= $this->cellXml(self::column($column).$number, $cell);
            }

            if ($content === '' && ! isset($heights[$number])) {
                continue;
            }

            $height = isset($heights[$number]) ? ' ht="'.$heights[$number].'" customHeight="1"' : '';
            $xml .= '<row r="'.$number.'"'.$height.'>'.$content.'</row>';
        }

        $xml .= '</sheetData>';

        if (isset($options['filter'])) {
            $xml .= '<autoFilter ref="'.$options['filter'].'"/>';
        }

        if (($options['merge'] ?? []) !== []) {
            $xml .= '<mergeCells count="'.count($options['merge']).'">';

            foreach ($options['merge'] as $range) {
                $xml .= '<mergeCell ref="'.$range.'"/>';
            }

            $xml .= '</mergeCells>';
        }

        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';

        if ($landscape) {
            $xml .= '<pageSetup paperSize="1" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        }

        return $xml.'</worksheet>';
    }

    /**
     * Deja fijas las primeras columnas y filas, para que el nombre y el
     * encabezado se vean al desplazarse.
     *
     * @param  array{0: int, 1: int}  $freeze
     */
    private function pane(array $freeze): string
    {
        [$columns, $rows] = $freeze;

        if ($columns === 0 && $rows === 0) {
            return '';
        }

        $topLeft = self::column($columns).($rows + 1);
        $active = match (true) {
            $columns > 0 && $rows > 0 => 'bottomRight',
            $rows > 0 => 'bottomLeft',
            default => 'topRight',
        };

        return '<pane'.($columns > 0 ? ' xSplit="'.$columns.'"' : '').($rows > 0 ? ' ySplit="'.$rows.'"' : '')
            .' topLeftCell="'.$topLeft.'" activePane="'.$active.'" state="frozen"/>'
            .'<selection pane="'.$active.'" activeCell="'.$topLeft.'" sqref="'.$topLeft.'"/>';
    }

    private function cellXml(string $reference, mixed $cell): string
    {
        [$value, $style] = is_array($cell) ? [$cell['value'] ?? null, $cell['style'] ?? 'default'] : [$cell, 'default'];

        if (! isset(self::STYLES[$style])) {
            throw new InvalidArgumentException("Estilo desconocido: {$style}");
        }

        $index = self::STYLES[$style];
        $attributes = ' r="'.$reference.'"'.($index > 0 ? ' s="'.$index.'"' : '');

        if ($value === null || $value === '') {
            // Una celda vacía con estilo conserva su borde o su fondo.
            return $index > 0 ? '<c'.$attributes.'/>' : '';
        }

        if (is_int($value) || is_float($value)) {
            return '<c'.$attributes.'><v>'.$value.'</v></c>';
        }

        return '<c'.$attributes.' t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
    }

    private function escape(string $text): string
    {
        // XML no admite caracteres de control, aunque vengan en un nombre.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Excel no admite en el nombre de una hoja []:*?/\, ni más de 31
     * caracteres, ni dos hojas con el mismo nombre.
     */
    private function uniqueName(string $name): string
    {
        $base = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name) ?? '', " '");
        $base = $base === '' ? 'Hoja' : $base;
        $taken = array_map(fn (array $sheet) => mb_strtolower($sheet['name']), $this->sheets);

        $candidate = mb_substr($base, 0, self::MAX_SHEET_NAME);

        for ($n = 2; in_array(mb_strtolower($candidate), $taken, true); $n++) {
            $suffix = " ({$n})";
            $candidate = mb_substr($base, 0, self::MAX_SHEET_NAME - mb_strlen($suffix)).$suffix;
        }

        return $candidate;
    }

    private function contentTypes(): string
    {
        $sheets = '';

        foreach (array_keys($this->sheets) as $index) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';
        $names = '';

        foreach ($this->sheets as $index => $sheet) {
            $name = $this->escape($sheet['name']);
            $sheets .= '<sheet name="'.$name.'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';

            if ($sheet['filter'] !== null) {
                // Excel guarda el rango del filtro con este nombre reservado.
                $range = preg_replace_callback('/([A-Z]+)(\d+)/', fn (array $match) => '$'.$match[1].'$'.$match[2], $sheet['filter']);
                $quoted = "'".str_replace("'", "''", $sheet['name'])."'";
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$index.'" hidden="1">'.$this->escape($quoted).'!'.$range.'</definedName>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView activeTab="0"/></bookViews>'
            .'<sheets>'.$sheets.'</sheets>'
            .($names !== '' ? '<definedNames>'.$names.'</definedNames>' : '')
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        $relationships = '';

        foreach (array_keys($this->sheets) as $index) {
            $relationships .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    /**
     * Los estilos de STYLES, en el mismo orden. Rojo y fondo rosa para lo que
     * se pierde (los colores de "Incorrecto" de Excel), gris en cursiva para lo
     * incompleto.
     */
    private function styles(): string
    {
        $font = fn (string $extra = '', int $size = 11, ?string $color = null) => '<font>'.$extra.'<sz val="'.$size.'"/>'
            .($color ? '<color rgb="'.$color.'"/>' : '').'<name val="Calibri"/><family val="2"/></font>';

        $fonts = [
            $font(),                           // 0 normal
            $font('<b/>'),                     // 1 negrita
            $font('<b/>', 14),                 // 2 título
            $font('<i/>', 10, 'FF71717A'),     // 3 nota gris
            $font('', 11, 'FF9C0006'),         // 4 rojo
            $font('<i/>', 11, 'FF71717A'),     // 5 gris en cursiva
            $font('<i/>', 11, 'FF9C0006'),     // 6 rojo en cursiva
            $font('<b/>', 11, 'FF92400E'),     // 7 sello provisional
            $font('<b/>', 11, 'FF047857'),     // 8 sello definitivo
        ];

        $fills = [
            '<fill><patternFill patternType="none"/></fill>',
            '<fill><patternFill patternType="gray125"/></fill>',
            '<fill><patternFill patternType="solid"><fgColor rgb="FFF4F4F5"/><bgColor indexed="64"/></patternFill></fill>', // 2 encabezado
            '<fill><patternFill patternType="solid"><fgColor rgb="FFFFC7CE"/><bgColor indexed="64"/></patternFill></fill>', // 3 rosa
        ];

        $side = fn (string $name) => '<'.$name.' style="thin"><color rgb="FFD4D4D8"/></'.$name.'>';
        $borders = [
            '<border><left/><right/><top/><bottom/><diagonal/></border>',
            '<border>'.$side('left').$side('right').$side('top').$side('bottom').'<diagonal/></border>',
        ];

        $center = '<alignment horizontal="center" vertical="center"/>';
        $xf = fn (int $numFmt, int $font, int $fill, int $border, string $alignment = '') => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0"'
            .($numFmt > 0 ? ' applyNumberFormat="1"' : '').($font > 0 ? ' applyFont="1"' : '').($fill > 0 ? ' applyFill="1"' : '').($border > 0 ? ' applyBorder="1"' : '')
            .($alignment !== '' ? ' applyAlignment="1">'.$alignment.'</xf>' : '/>');

        $cellXfs = [
            $xf(0, 0, 0, 0),                    // default
            $xf(0, 2, 0, 0),                    // title
            $xf(0, 1, 0, 0),                    // subtitle
            $xf(0, 3, 0, 0),                    // muted
            $xf(0, 1, 2, 1, '<alignment horizontal="center" vertical="center" wrapText="1"/>'), // header
            $xf(0, 0, 0, 1, '<alignment vertical="center"/>'), // text
            $xf(0, 0, 0, 1, $center),           // center
            $xf(164, 0, 0, 1, $center),         // score
            $xf(164, 4, 3, 1, $center),         // score-fail
            $xf(164, 5, 0, 1, $center),         // score-pending
            $xf(164, 6, 3, 1, $center),         // score-pending-fail
            $xf(1, 0, 0, 1, $center),           // integer
            $xf(1, 4, 3, 1, $center),           // integer-fail
            $xf(0, 1, 0, 1, '<alignment vertical="center"/>'), // bold
            $xf(164, 1, 0, 1, $center),         // bold-score
            $xf(0, 7, 0, 0),                    // seal-provisional
            $xf(0, 8, 0, 0),                    // seal-definitive
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts>'
            .'<fonts count="'.count($fonts).'">'.implode('', $fonts).'</fonts>'
            .'<fills count="'.count($fills).'">'.implode('', $fills).'</fills>'
            .'<borders count="'.count($borders).'">'.implode('', $borders).'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($cellXfs).'">'.implode('', $cellXfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
