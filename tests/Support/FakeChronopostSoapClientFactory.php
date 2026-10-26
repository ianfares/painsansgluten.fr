<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Chronopost\SoapClientFactory;
use SoapClient;
use SoapFault;

/**
 * Remplace le réseau Chronopost en test : le vrai SoapClient (WSDL embarqué)
 * analyse une réponse XML enregistrée au lieu d'appeler le service.
 * `$response = null` simule une panne réseau (SoapFault).
 */
class FakeChronopostSoapClientFactory extends SoapClientFactory
{
    /** @var list<string> requêtes XML envoyées */
    public array $requests = [];

    public function __construct(public ?string $response) {}

    public static function fixture(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/Fixtures/chronopost/'.$name);
    }

    public static function errorResponse(int $code, string $message): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<ns1:recherchePointChronopostInterResponse xmlns:ns1="http://cxf.rechercheBt.soap.chronopost.fr/">'
            .'<return><errorCode>'.$code.'</errorCode><errorMessage>'.$message.'</errorMessage></return>'
            .'</ns1:recherchePointChronopostInterResponse></soap:Body></soap:Envelope>';
    }

    public function make(string $wsdl, int $timeoutSeconds): SoapClient
    {
        $factory = $this;

        return new class($wsdl, $factory) extends SoapClient
        {
            public function __construct(string $wsdl, private readonly FakeChronopostSoapClientFactory $factory)
            {
                parent::__construct($wsdl, ['exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS]);
            }

            public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
            {
                $this->factory->requests[] = $request;

                if ($this->factory->response === null) {
                    throw new SoapFault('HTTP', 'Could not connect to host');
                }

                return $this->factory->response;
            }
        };
    }
}
