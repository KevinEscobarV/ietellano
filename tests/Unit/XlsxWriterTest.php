<?php

use App\Support\XlsxWriter;

/**
 * @return array<string, string> ruta dentro del .xlsx => contenido
 */
function xlsxParts(XlsxWriter $book): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $book->save($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $parts = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $parts[$name] = $zip->getFromIndex($i);
    }

    $zip->close();
    unlink($path);

    return $parts;
}

it('nombra las columnas como Excel', function () {
    expect(XlsxWriter::column(0))->toBe('A')
        ->and(XlsxWriter::column(25))->toBe('Z')
        ->and(XlsxWriter::column(26))->toBe('AA')
        ->and(XlsxWriter::column(701))->toBe('ZZ')
        ->and(XlsxWriter::column(702))->toBe('AAA');
});

it('arma un libro válido: cada parte es XML bien formado', function () {
    $parts = xlsxParts((new XlsxWriter)
        ->addSheet('Uno', [['a', 1]])
        ->addSheet('Dos', [['b', 2.5]], ['filter' => 'A1:B1', 'freeze' => [1, 1], 'merge' => ['A3:B3'], 'landscape' => true]));

    expect(array_keys($parts))->toEqualCanonicalizing([
        '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml',
        'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml',
    ]);

    foreach ($parts as $name => $content) {
        expect(simplexml_load_string($content))->not->toBeFalse("{$name} no es XML válido");
    }

    // Excel exige un estilo por cada nombre de STYLES.
    $styles = simplexml_load_string($parts['xl/styles.xml']);
    expect((int) $styles->cellXfs['count'])->toBe(count(XlsxWriter::STYLES));
});

it('escribe los números como números y el texto en línea, escapado', function () {
    $sheet = xlsxParts((new XlsxWriter)->addSheet('Notas', [
        ['Estudiante', 'Nota'],
        [XlsxWriter::cell('Ñáñez & <José>', 'text'), XlsxWriter::cell(2.5, 'score-fail')],
        [],
        ['=SUMA(1)', 4],
    ]))['xl/worksheets/sheet1.xml'];

    expect($sheet)
        ->toContain('<c r="B2" s="'.XlsxWriter::STYLES['score-fail'].'"><v>2.5</v></c>')
        ->toContain('Ñáñez &amp; &lt;José&gt;')
        // Lo que parece fórmula queda como texto: el libro no calcula nada.
        ->toContain('<c r="A4" t="inlineStr"><is><t xml:space="preserve">=SUMA(1)</t></is></c>')
        ->toContain('<c r="B4"><v>4</v></c>')
        ->not->toContain('<f>')
        ->not->toContain('<row r="3"');
});

it('una celda vacía con estilo se escribe para conservar su borde', function () {
    $sheet = xlsxParts((new XlsxWriter)->addSheet('X', [[null, XlsxWriter::cell(null, 'center'), '']]))['xl/worksheets/sheet1.xml'];

    expect($sheet)
        ->toContain('<c r="B1" s="'.XlsxWriter::STYLES['center'].'"/>')
        ->not->toContain('r="A1"')
        ->not->toContain('r="C1"');
});

it('deja fijo el encabezado y el filtro con su rango nombrado', function () {
    $parts = xlsxParts((new XlsxWriter)->addSheet("Ciclo 5'", [['a']], ['freeze' => [2, 6], 'filter' => 'A6:F20']));

    expect($parts['xl/worksheets/sheet1.xml'])
        ->toContain('<pane xSplit="2" ySplit="6" topLeftCell="C7" activePane="bottomRight" state="frozen"/>')
        ->toContain('<autoFilter ref="A6:F20"/>')
        ->and($parts['xl/workbook.xml'])
        ->toContain('<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">&apos;Ciclo 5&apos;!$A$6:$F$20</definedName>');
});

it('limpia los nombres de hoja que Excel no admite y no los repite', function () {
    $workbook = simplexml_load_string(xlsxParts((new XlsxWriter)
        ->addSheet('Matemáticas 5/1: [notas]?', [])
        ->addSheet('Una hoja con un nombre larguísimo que no cabe', [])
        ->addSheet('Una hoja con un nombre larguísimo que no cabe', [])
        ->addSheet('', []))['xl/workbook.xml']);

    $names = [];

    foreach ($workbook->sheets->sheet as $sheet) {
        $names[] = (string) $sheet['name'];
    }

    expect($names[0])->not->toMatch('/[\[\]:*?\/\\\\]/')
        ->and($names[0])->toStartWith('Matemáticas 5 1')
        ->and(mb_strlen($names[1]))->toBe(31)
        ->and($names[2])->toEndWith(' (2)')
        ->and(mb_strlen($names[2]))->toBe(31)
        ->and($names[3])->toBe('Hoja');
});

it('no guarda un libro sin hojas', function () {
    (new XlsxWriter)->save(tempnam(sys_get_temp_dir(), 'xlsx'));
})->throws(RuntimeException::class);

it('no acepta un estilo que no existe', function () {
    (new XlsxWriter)->addSheet('X', [[XlsxWriter::cell('a', 'rosado')]]);
})->throws(InvalidArgumentException::class);
