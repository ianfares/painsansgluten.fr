<?php

declare(strict_types=1);

use App\Services\Chronopost\SoapClientFactory;

test('le délai de lecture SOAP est réellement appliqué (Chronopost lent)', function () {
    $port = random_int(20000, 40000);
    $server = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", dirname(__DIR__, 2).'/Fixtures/chronopost/slow-server.php'],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
    );
    usleep(300_000); // démarrage du serveur local

    try {
        $client = (new SoapClientFactory)->make(resource_path('chronopost/PointRelaisServiceWS.wsdl'), 1);
        $client->__setLocation("http://127.0.0.1:{$port}/");

        $start = microtime(true);
        $fault = null;
        try {
            $client->__soapCall('recherchePointChronopostInter', [array_fill_keys(
                ['accountNumber', 'password', 'address', 'zipCode', 'city', 'countryCode', 'type', 'productCode', 'service', 'weight', 'shippingDate', 'maxPointChronopost', 'maxDistanceSearch', 'holidayTolerant', 'language', 'version'],
                '',
            )]);
        } catch (SoapFault $e) {
            $fault = $e; // attendu : délai de lecture dépassé
        }
        $elapsed = microtime(true) - $start;

        expect($fault?->getMessage())->toContain('Error Fetching http')
            ->and($elapsed)->toBeGreaterThan(0.9)->toBeLessThan(3.0)
            ->and(ini_get('default_socket_timeout'))->not->toBe('1'); // réglage global restauré
    } finally {
        proc_terminate($server, 9); // SIGKILL : ne pas attendre la fin du sleep du faux serveur
        proc_close($server);
    }
});
