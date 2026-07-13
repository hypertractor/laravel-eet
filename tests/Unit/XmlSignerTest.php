<?php

namespace Pomocnik\Eet\Tests\Unit;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Crypto\XmlSigner;
use Pomocnik\Eet\Exceptions\EetException;
use Pomocnik\Eet\Xml\XmlBuilder;
use Pomocnik\Eet\DTOs\EetRequest;

class XmlSignerTest extends TestCase
{
    private XmlSigner $signer;
    private CertificateManager $certManager;
    private XmlBuilder $xmlBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $certPath = __DIR__.'/../../../../docs/CA_EET-Playground-CZ00000019.p12';

        if (! file_exists($certPath)) {
            $this->markTestSkipped('Playground certificate not found');
        }

        $this->certManager = new CertificateManager($certPath, 'aaaa1111');
        $this->signer = new XmlSigner();
        $this->xmlBuilder = new XmlBuilder();
    }

    private function createTestRequest(): EetRequest
    {
        return new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
        );
    }

    private function createSignedEnvelope(): string
    {
        $request = $this->createTestRequest();
        $soapXml = $this->xmlBuilder->buildSoapEnvelope($request);

        return $this->signer->signSoapEnvelope($soapXml, $this->certManager);
    }

    public function test_sign_returns_valid_xml(): void
    {
        $signed = $this->createSignedEnvelope();

        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($signed), 'Signed XML must be parseable');
    }

    public function test_signed_contains_wsse_security_header(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('wsse:Security', $signed);
        $this->assertStringContainsString('wsse:BinarySecurityToken', $signed);
    }

    public function test_signed_contains_ds_signature(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('ds:Signature', $signed);
        $this->assertStringContainsString('ds:SignedInfo', $signed);
        $this->assertStringContainsString('ds:SignatureValue', $signed);
        $this->assertStringContainsString('ds:KeyInfo', $signed);
    }

    public function test_signed_contains_correct_algorithms(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('http://www.w3.org/2001/10/xml-exc-c14n#', $signed);
        $this->assertStringContainsString('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $signed);
        $this->assertStringContainsString('http://www.w3.org/2001/04/xmlenc#sha256', $signed);
    }

    public function test_signed_contains_inclusive_namespaces(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('ec:InclusiveNamespaces', $signed);
        $this->assertStringContainsString('PrefixList="soapenv v4"', $signed);
    }

    public function test_signed_contains_body_reference(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertMatchesRegularExpression('/URI="#id-[a-f0-9]+"/', $signed);
    }

    public function test_signed_contains_digest_value(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('ds:DigestValue', $signed);
        $this->assertStringContainsString('ds:SignatureValue', $signed);
    }

    public function test_signed_body_has_wsuid(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('wsu:Id', $signed);
    }

    public function test_signed_contains_binary_security_token(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary"', $signed);
        $this->assertStringContainsString('ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"', $signed);
    }

    public function test_signed_contains_trzba_data(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('eic_popl="CZ00000019"', $signed);
        $this->assertStringContainsString('id_jednotky="303"', $signed);
        $this->assertStringContainsString('celk_trzba="188580.00"', $signed);
    }

    public function test_signed_signature_value_is_not_empty(): void
    {
        $signed = $this->createSignedEnvelope();

        $doc = new DOMDocument();
        $doc->loadXML($signed);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $sigValue = $xpath->query('//ds:SignatureValue')->item(0);
        $this->assertNotNull($sigValue);
        $this->assertNotEmpty(trim($sigValue->nodeValue));
    }

    public function test_signed_digest_value_is_base64(): void
    {
        $signed = $this->createSignedEnvelope();

        $doc = new DOMDocument();
        $doc->loadXML($signed);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $digestValue = $xpath->query('//ds:DigestValue')->item(0);
        $this->assertNotNull($digestValue);

        $decoded = base64_decode(trim($digestValue->nodeValue), true);
        $this->assertNotFalse($decoded);
        $this->assertGreaterThan(0, strlen($decoded));
    }

    public function test_signed_unique_ids(): void
    {
        $signed = $this->createSignedEnvelope();

        preg_match_all('/Id="([^"]+)"/', $signed, $matches);
        $ids = $matches[1];

        $this->assertGreaterThan(1, count($ids));
        $this->assertCount(count($ids), array_unique($ids), 'All Id attributes must be unique');
    }

    public function test_invalid_xml_throws_exception(): void
    {
        $this->expectException(EetException::class);
        $this->expectExceptionMessage('Failed to parse SOAP XML');

        $xml = '<?xml version="1.0"?>not valid xml<';
        $this->signer->signSoapEnvelope($xml, $this->certManager);
    }

    public function test_missing_body_throws_exception(): void
    {
        $this->expectException(EetException::class);
        $this->expectExceptionMessage('SOAP Body not found');

        $this->signer->signSoapEnvelope(
            '<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Header/></soapenv:Envelope>',
            $this->certManager,
        );
    }

    public function test_missing_header_throws_exception(): void
    {
        $this->expectException(EetException::class);
        $this->expectExceptionMessage('SOAP Header not found');

        $this->signer->signSoapEnvelope(
            '<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><data/></soapenv:Body></soapenv:Envelope>',
            $this->certManager,
        );
    }

    public function test_signed_envelope_has_soap_structure(): void
    {
        $signed = $this->createSignedEnvelope();

        $this->assertStringContainsString('soapenv:Envelope', $signed);
        $this->assertStringContainsString('soapenv:Header', $signed);
        $this->assertStringContainsString('soapenv:Body', $signed);
    }

    public function test_exclusive_c14n_used_for_body_digest(): void
    {
        $signed = $this->createSignedEnvelope();

        $doc = new DOMDocument();
        $doc->loadXML($signed);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $transform = $xpath->query('//ds:Transform')->item(0);
        $this->assertNotNull($transform);
        $this->assertSame('http://www.w3.org/2001/10/xml-exc-c14n#', $transform->getAttribute('Algorithm'));
    }
}
