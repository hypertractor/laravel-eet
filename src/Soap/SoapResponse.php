<?php

namespace Pomocnik\Eet\Soap;

use DOMDocument;
use DOMXPath;
use Pomocnik\Eet\DTOs\EetResult;
use Pomocnik\Eet\Exceptions\SoapException;

class SoapResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $fikCode = null,
        public readonly ?string $testFikCode = null,
        public readonly ?int $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $warningCode = null,
        public readonly ?string $rawXml = null,
    ) {}

    /**
     * Parsova XML odpoved z EET serveru (Odpoved type).
     */
    public static function fromXml(string $xml): self
    {
        $doc = new DOMDocument();

        if (! $doc->loadXML($xml)) {
            throw new SoapException('Cannot parse SOAP response XML');
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('v4', 'http://fs.gov.cz/eet/schema/v4');
        $xpath->registerNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');

        // Najit Odpoved element
        $odpoved = $xpath->query('//v4:Odpoved')->item(0);

        if ($odpoved === null) {
            // Zkusit bez namespace prefixu (nektere odpovedi nemusi mit prefix)
            $odpoved = $xpath->query('//*[local-name()="Odpoved"]')->item(0);
        }

        if ($odpoved === null) {
            throw new SoapException('Odpoved element not found in response');
        }

        // Zkontrolovat Potvrzeni (uspech)
        $potvrzeni = $xpath->query('.//v4:Potvrzeni', $odpoved)->item(0)
            ?? $xpath->query('.//*[local-name()="Potvrzeni"]', $odpoved)->item(0);

        if ($potvrzeni !== null) {
            $fikCode = $potvrzeni->getAttribute('pok');
            $testFik = $potvrzeni->getAttribute('test') ?: null;

            return new self(
                success: true,
                fikCode: $fikCode,
                testFikCode: $testFik,
                rawXml: $xml,
            );
        }

        // Zkontrolovat Chyba (neuspech)
        $chyba = $xpath->query('.//v4:Chyba', $odpoved)->item(0)
            ?? $xpath->query('.//*[local-name()="Chyba"]', $odpoved)->item(0);

        if ($chyba !== null) {
            $errorCode = (int) $chyba->getAttribute('kod');
            $errorMessage = $chyba->textContent;

            return new self(
                success: false,
                errorCode: $errorCode,
                errorMessage: $errorMessage,
                rawXml: $xml,
            );
        }

        throw new SoapException('Response contains neither Potvrzeni nor Chyba element');
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'fik_code' => $this->fikCode,
            'test_fik_code' => $this->testFikCode,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'warning_code' => $this->warningCode,
        ];
    }
}
