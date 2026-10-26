<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Chronopost\SoapClientFactory;
use SoapClient;
use SoapFault;

/**
 * Remplace le réseau Chronopost en test : le vrai SoapClient (WSDL embarqué)
 * analyse une réponse XML enregistrée au lieu d'appeler le service.
 * `$response = null` simule une panne réseau (SoapFault). Des réponses
 * propres à une méthode SOAP peuvent être données dans `$responsesByMethod`
 * (ex. `['cancelListSkybill' => '<soap:Envelope>…']`).
 */
class FakeChronopostSoapClientFactory extends SoapClientFactory
{
    /** @var list<string> requêtes XML envoyées */
    public array $requests = [];

    /** Appelé à chaque requête (ex. : observer l'état de la BDD pendant l'appel). */
    public ?\Closure $onRequest = null;

    /** @var array<string, string|null> méthode SOAP => réponse (null = panne) */
    public array $responsesByMethod = [];

    public function __construct(public ?string $response) {}

    public static function cancelResponse(int $code = 0, string $message = ''): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<ns1:cancelListSkybillResponse xmlns:ns1="http://cxf.tracking.soap.chronopost.fr/">'
            .'<return><errorCode>'.$code.'</errorCode><errorMessage>'.$message.'</errorMessage><statusCode>0</statusCode></return>'
            .'</ns1:cancelListSkybillResponse></soap:Body></soap:Envelope>';
    }

    public static function shippingErrorResponse(int $code, string $message): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<ns1:shippingMultiParcelV4Response xmlns:ns1="http://cxf.shipping.soap.chronopost.fr/">'
            .'<return><errorCode>'.$code.'</errorCode><errorMessage>'.$message.'</errorMessage></return>'
            .'</ns1:shippingMultiParcelV4Response></soap:Body></soap:Envelope>';
    }

    /** Réponse pour la requête donnée : propre à la méthode si configurée, sinon la réponse par défaut. */
    public function responseFor(string $request): ?string
    {
        foreach ($this->responsesByMethod as $method => $response) {
            if (str_contains($request, ':'.$method.'>')) {
                return $response;
            }
        }

        return $this->response;
    }

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
                if ($this->factory->onRequest !== null) {
                    ($this->factory->onRequest)();
                }

                $response = $this->factory->responseFor($request);
                if ($response === null) {
                    throw new SoapFault('HTTP', 'Could not connect to host');
                }

                return $response;
            }
        };
    }
}
