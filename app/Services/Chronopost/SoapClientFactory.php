<?php

declare(strict_types=1);

namespace App\Services\Chronopost;

use SoapClient;

/**
 * Crée les clients SOAP Chronopost. Isolé pour que les tests injectent un
 * client qui rejoue une réponse enregistrée (aucun appel réel, QUALITE.md §6.2).
 */
class SoapClientFactory
{
    /**
     * @param  string  $wsdl  chemin du WSDL embarqué
     */
    public function make(string $wsdl, int $timeoutSeconds): SoapClient
    {
        $client = new TimedSoapClient($wsdl, [
            'connection_timeout' => $timeoutSeconds,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
        ]);
        // Délai de lecture de la réponse (voir TimedSoapClient : connection_timeout ne couvre que la connexion).
        $client->setReadTimeout($timeoutSeconds);

        return $client;
    }
}
