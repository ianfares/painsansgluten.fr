<?php

declare(strict_types=1);

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Remplace le réseau de la librairie Stripe en test : enregistre chaque
 * appel et renvoie une réponse JSON préparée (jamais d'appel réel).
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<mixed>, params: array<mixed>}> */
    public array $requests = [];

    /** @param array<string, mixed> $response */
    public function __construct(public array $response = [], public int $status = 200) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'headers' => $headers, 'params' => $params];

        return [json_encode($this->response), $this->status, ['request-id' => 'req_test']];
    }
}
