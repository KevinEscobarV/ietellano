<?php

use App\Services\BoletinService;

it('da el mismo promedio sin importar el orden de las notas', function () {
    // Suman 21.56 y el promedio cae justo en 2.695. El boletín ordena las
    // líneas por nombre y el certificado por área oficial.
    $boletin = [2.19, 2.81, 2.15, 2.29, 2.62, 2.63, 3.17, 3.7];
    $certificado = [2.19, 2.81, 2.62, 2.15, 3.7, 2.29, 2.63, 3.17];

    expect(BoletinService::average($boletin))->toBe(2.7)
        ->and(BoletinService::average($certificado))->toBe(2.7);
});

it('no cuenta las notas que faltan', function () {
    expect(BoletinService::average([4.0, null, 3.0]))->toBe(3.5)
        ->and(BoletinService::average([null, null]))->toBeNull()
        ->and(BoletinService::average([]))->toBeNull();
});
