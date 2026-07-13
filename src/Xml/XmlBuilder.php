<?php

namespace Pomocnik\Eet\Xml;

use DOMDocument;
use Illuminate\Support\Str;
use Pomocnik\Eet\DTOs\EetRequest;

class XmlBuilder
{
    private const NAMESPACE_V4 = 'http://fs.gov.cz/eet/schema/v4';
    private const SOAP_NAMESPACE = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * Sestavi Trzba XML (data zpravy) bez SOAP obalky.
     */
    public function buildTrzbaXml(EetRequest $request): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $trzba = $doc->createElementNS(self::NAMESPACE_V4, 'v4:Trzba');
        $doc->appendChild($trzba);

        // Hlavicka
        $hlavicka = $doc->createElementNS(self::NAMESPACE_V4, 'v4:Hlavicka');
        $hlavicka->setAttribute('uuid_zpravy', $request->uuidZpravy ?? (string) Str::uuid());
        $hlavicka->setAttribute('dat_odesl', now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'));
        $hlavicka->setAttribute('prvni_zaslani', $request->prvniZaslani ? 'true' : 'false');

        if ($request->overeni) {
            $hlavicka->setAttribute('overeni', 'true');
        }

        $trzba->appendChild($hlavicka);

        // Data
        $data = $doc->createElementNS(self::NAMESPACE_V4, 'v4:Data');
        $data->setAttribute('eic_popl', $request->eicPopl);
        $data->setAttribute('id_jednotky', $request->idJednotky);
        $data->setAttribute('id_pokl', $request->idPokl);
        $data->setAttribute('porad_cis', $request->poradCis);
        $data->setAttribute('dat_trzby', $this->normalizeDateTime($request->datTrzby));
        $data->setAttribute('celk_trzba', $request->celkTrzba);

        if ($request->urcenoCerpZuct !== null) {
            $data->setAttribute('urceno_cerp_zuct', $request->urcenoCerpZuct);
        }

        if ($request->cerpZuct !== null) {
            $data->setAttribute('cerp_zuct', $request->cerpZuct);
        }

        if ($request->eicPoverujiciho !== null) {
            $data->setAttribute('eic_poverujiciho', $request->eicPoverujiciho);
        }

        if ($request->povereniVicePopl !== null) {
            $data->setAttribute('povereni_vice_popl', $request->povereniVicePopl ? 'true' : 'false');
        }

        $trzba->appendChild($data);

        return $doc->saveXML();
    }

    /**
     * Sestavi kompletni SOAP Envelope s Trzba daty (bez podpisu).
     */
    public function buildSoapEnvelope(EetRequest $request): string
    {
        $trzbaXml = $this->buildTrzbaXml($request);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        // SOAP Envelope
        $envelope = $doc->createElementNS(self::SOAP_NAMESPACE, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:soapenv', self::SOAP_NAMESPACE);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:v4', self::NAMESPACE_V4);

        // SOAP Header (prazdny, XmlSigner ho naplni)
        $header = $doc->createElementNS(self::SOAP_NAMESPACE, 'soapenv:Header');
        $envelope->appendChild($header);

        // SOAP Body
        $body = $doc->createElementNS(self::SOAP_NAMESPACE, 'soapenv:Body');

        // Import Trzba XML do Body
        $trzbaDoc = new DOMDocument();
        $trzbaDoc->loadXML($trzbaXml);
        $importedTrzba = $doc->importNode($trzbaDoc->documentElement, true);

        // Odstran redundantni xmlns:v4 deklaraci (nastavuje se na Envelopu)
        $importedTrzba->removeAttribute('xmlns:v4');

        $body->appendChild($importedTrzba);

        $envelope->appendChild($body);

        $doc->appendChild($envelope);

        return $doc->saveXML();
    }

    /**
     * Sestavi kompletni SOAP Envelope s podepisem.
     */
    public function buildSignedSoapEnvelope(EetRequest $request, \Pomocnik\Eet\Certificates\CertificateManager $certManager): string
    {
        $unsignedEnvelope = $this->buildSoapEnvelope($request);

        $signer = new \Pomocnik\Eet\Crypto\XmlSigner();

        return $signer->signSoapEnvelope($unsignedEnvelope, $certManager);
    }

    /**
     * Normalizuje format datetime do ISO 8601 s UTC (Y-m-d\TH:i:s\Z).
     * XSD vyzaduje: \d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(Z|[+\-]\d\d:\d\d)
     */
    private function normalizeDateTime(string $value): string
    {
        // Uz obsahuje Z nebo offset?
        if (preg_match('/(Z|[+\-]\d\d:\d\d)$/', $value)) {
            return $value;
        }

        // Bez offsetu — interpretuj jako UTC
        return $value.'Z';
    }
}
