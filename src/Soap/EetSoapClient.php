<?php

namespace Pomocnik\Eet\Soap;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Exceptions\SoapException;
use Pomocnik\Eet\Xml\XmlBuilder;

class EetSoapClient
{
    private Client $httpClient;

    public function __construct(
        private readonly CertificateManager $certManager,
        private readonly XmlBuilder $xmlBuilder,
    ) {
        $this->httpClient = $this->buildHttpClient();
    }

    /**
     * Odesle datovou zpravu evidovane trzby na EET endpoint.
     */
    public function sendRequest(\Pomocnik\Eet\DTOs\EetRequest $request, string $endpoint): SoapResponse
    {
        // Sestavit a podepsat SOAP obalku
        $signedEnvelope = $this->xmlBuilder->buildSignedSoapEnvelope($request, $this->certManager);

        try {
            $response = $this->httpClient->post($endpoint, [
                'body' => $signedEnvelope,
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => 'http://fs.gov.cz/eet/OdeslaniTrzby',
                ],
            ]);

            $responseBody = (string) $response->getBody();

            return SoapResponse::fromXml($responseBody);
        } catch (GuzzleException $e) {
            throw new SoapException(
                "SOAP communication failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    // ─── Interni metody ────────────────────────────────────────────

    private function buildHttpClient(): Client
    {
        $config = [
            'timeout' => (float) config('eet.timeouts.soap', 30.0),
            'verify' => $this->buildSslConfig(),
        ];

        return new Client($config);
    }

    private function buildSslConfig(): array|bool
    {
        $testMode = config('eet.test_mode', true);

        if ($testMode) {
            $caRoot = config('eet.ca_certificates.playground_root');
            $caSub = config('eet.ca_certificates.playground_sub');

            if ($caRoot && $caSub && file_exists($caRoot) && file_exists($caSub)) {
                return [
                    'cafile' => $caRoot,
                    'capath' => dirname($caRoot),
                ];
            }
        }

        // V produkci nebo bez specifickych CA - pouzij systemove
        return true;
    }
}
