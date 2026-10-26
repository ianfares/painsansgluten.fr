<?php

declare(strict_types=1);

namespace App\Services\Chronopost;

use SoapClient;

/**
 * Client SOAP dont le délai de LECTURE est réellement borné.
 *
 * Pourquoi : `connection_timeout` ne couvre que la connexion, et l'option
 * `http.timeout` du stream_context est ignorée par SoapClient ; la lecture
 * de la réponse suit `default_socket_timeout` (60 s par défaut). Sans ce
 * réglage, un Chronopost lent bloquerait le tunnel ou le BO une minute.
 * Le réglage est posé le temps de l'appel puis restauré.
 */
class TimedSoapClient extends SoapClient
{
    private int $readTimeoutSeconds = 60;

    public function setReadTimeout(int $seconds): void
    {
        $this->readTimeoutSeconds = $seconds;
    }

    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
    {
        $previous = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) $this->readTimeoutSeconds);

        try {
            return parent::__doRequest($request, $location, $action, $version, $oneWay);
        } finally {
            ini_set('default_socket_timeout', $previous);
        }
    }
}
