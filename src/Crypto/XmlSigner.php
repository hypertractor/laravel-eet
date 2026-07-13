<?php

namespace Pomocnik\Eet\Crypto;

use DOMDocument;
use DOMXPath;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Exceptions\EetException;

class XmlSigner
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const WSSE_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const WSU_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';
    private const EC_NS = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    private const NAMESPACE_V4 = 'http://fs.gov.cz/eet/schema/v4';

    public function signSoapEnvelope(string $soapXml, CertificateManager $certManager): string
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;

        libxml_use_internal_errors(true);

        if (! $doc->loadXML($soapXml)) {
            throw new EetException('Failed to parse SOAP XML for signing');
        }

        libxml_clear_errors();

        $bodyId = $this->generateId('id');
        $tokenId = $this->generateId('X509');
        $sigId = $this->generateId('SIG');
        $kiId = $this->generateId('KI');
        $strId = $this->generateId('STR');

        $body = $this->findElement($doc, 'Body', self::SOAP_NS);
        if (! $body) {
            throw new EetException('SOAP Body not found');
        }
        $body->setAttributeNS(self::WSU_NS, 'wsu:Id', $bodyId);

        $certPem = $certManager->getCertificatePem();
        $certBase64 = base64_encode($this->pemToDer($certPem));
        $privateKey = $certManager->getOpenSslPrivateKey();

        // 1. Compute body C14N digest using Exclusive C14N with PrefixList="v4"
        //    (matches the ds:Transform applied by the server during verification)
        $bodyC14n = $body->C14N(true, false, null, ['v4']);
        $bodyDigest = base64_encode(hash('sha256', $bodyC14n, true));

        // 2. Build WS-Security header with placeholder SignatureValue
        $header = $this->findElement($doc, 'Header', self::SOAP_NS);
        if (! $header) {
            throw new EetException('SOAP Header not found');
        }

        $this->buildWsseHeaderInPlace(
            header: $header,
            bodyRef: '#'.$bodyId,
            tokenId: $tokenId,
            certBase64: $certBase64,
            sigId: $sigId,
            signatureValue: '',
            bodyDigest: $bodyDigest,
            kiId: $kiId,
            strId: $strId,
        );

        // 3. C14N the SignedInfo using Exclusive C14N with PrefixList="soapenv v4"
        //    (matches the ds:CanonicalizationMethod applied by the server during verification)
        $signature = $this->findElement($doc, 'Signature', self::DS_NS);
        if (! $signature) {
            throw new EetException('ds:Signature not found after header build');
        }
        $signedInfo = $signature->getElementsByTagNameNS(self::DS_NS, 'SignedInfo')->item(0);
        $signedInfoC14n = $signedInfo->C14N(true, false, null, ['soapenv', 'v4']);

        // 4. Sign the canonicalized SignedInfo
        $sig = '';
        openssl_sign($signedInfoC14n, $sig, $privateKey, OPENSSL_ALGO_SHA256);

        // 5. Set the real signature value
        $sigValueEl = $signature->getElementsByTagNameNS(self::DS_NS, 'SignatureValue')->item(0);
        $sigValueEl->nodeValue = base64_encode($sig);

        return $doc->saveXML();
    }

    // ─── WS-Security Header — built directly in main doc ───────────

    private function buildWsseHeaderInPlace(
        \DOMElement $header,
        string $bodyRef,
        string $tokenId,
        string $certBase64,
        string $sigId,
        string $signatureValue,
        string $bodyDigest,
        string $kiId,
        string $strId,
    ): void {
        $doc = $header->ownerDocument;

        // wsse:Security
        $security = $doc->createElementNS(self::WSSE_NS, 'wsse:Security');
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsse', self::WSSE_NS);
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', self::WSU_NS);
        $header->appendChild($security);

        // wsse:BinarySecurityToken
        $bst = $doc->createElementNS(self::WSSE_NS, 'wsse:BinarySecurityToken');
        $bst->setAttribute('EncodingType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
        $bst->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
        $bst->setAttributeNS(self::WSU_NS, 'wsu:Id', $tokenId);
        $bst->nodeValue = $certBase64;
        $security->appendChild($bst);

        // ds:Signature
        $signature = $doc->createElementNS(self::DS_NS, 'ds:Signature');
        $signature->setAttribute('Id', $sigId);
        $security->appendChild($signature);

        // ds:SignedInfo — namespace declarations will be auto-inherited from ancestors
        $signedInfo = $doc->createElementNS(self::DS_NS, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $c14nMethod = $doc->createElementNS(self::DS_NS, 'ds:CanonicalizationMethod');
        $c14nMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $inc1 = $doc->createElementNS(self::EC_NS, 'ec:InclusiveNamespaces');
        $inc1->setAttribute('PrefixList', 'soapenv v4');
        $c14nMethod->appendChild($inc1);
        $signedInfo->appendChild($c14nMethod);

        $sigMethod = $doc->createElementNS(self::DS_NS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sigMethod);

        $reference = $doc->createElementNS(self::DS_NS, 'ds:Reference');
        $reference->setAttribute('URI', $bodyRef);
        $transforms = $doc->createElementNS(self::DS_NS, 'ds:Transforms');
        $transform = $doc->createElementNS(self::DS_NS, 'ds:Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $inc2 = $doc->createElementNS(self::EC_NS, 'ec:InclusiveNamespaces');
        $inc2->setAttribute('PrefixList', 'v4');
        $transform->appendChild($inc2);
        $transforms->appendChild($transform);
        $reference->appendChild($transforms);
        $digestMethod = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);
        $digestValue = $doc->createElementNS(self::DS_NS, 'ds:DigestValue');
        $digestValue->nodeValue = $bodyDigest;
        $reference->appendChild($digestValue);
        $signedInfo->appendChild($reference);

        $sigValue = $doc->createElementNS(self::DS_NS, 'ds:SignatureValue');
        $sigValue->nodeValue = $signatureValue;
        $signature->appendChild($sigValue);

        $keyInfo = $doc->createElementNS(self::DS_NS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $kiId);
        $signature->appendChild($keyInfo);

        $str = $doc->createElementNS(self::WSSE_NS, 'wsse:SecurityTokenReference');
        $str->setAttributeNS(self::WSU_NS, 'wsu:Id', $strId);
        $keyInfo->appendChild($str);

        $ref = $doc->createElementNS(self::WSSE_NS, 'wsse:Reference');
        $ref->setAttribute('URI', '#'.$tokenId);
        $ref->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
        $str->appendChild($ref);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function findElement(DOMDocument $doc, string $localName, string $namespace): ?\DOMElement
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ns', $namespace);
        $nodes = $xpath->query('//ns:'.$localName);

        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function generateId(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(16));
    }

    private function pemToDer(string $pem): string
    {
        $clean = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"],
            '',
            $pem
        );

        return base64_decode($clean, true);
    }
}
