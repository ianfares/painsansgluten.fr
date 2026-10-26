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
        return new SoapClient($wsdl, [
            'connection_timeout' => $timeoutSeconds,
            // Délai de lecture de la réponse (connection_timeout ne couvre que la connexion).
            'stream_context' => stream_context_create(['http' => ['timeout' => $timeoutSeconds]]),
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
        ]);
    }
}
