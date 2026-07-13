<?php

namespace Pomocnik\Eet\Certificates;

use Pomocnik\Eet\Exceptions\CertificateException;

class CertificateManager
{
    private ?string $parsedCert = null;
    private ?string $certificatePem = null;
    private ?string $privateKeyPem = null;

    public function __construct(
        private readonly string $p12Path,
        private readonly string $password,
    ) {
        if (! file_exists($p12Path)) {
            throw new CertificateException("Certificate file not found: {$p12Path}");
        }

        $this->loadCertificate();
    }

    /**
     * Vrati PEM retezec zakladniho certifikatu (pro vlozeni do SOAP hlavicky).
     */
    public function getCertificatePem(): string
    {
        return $this->certificatePem;
    }

    /**
     * Vrati PEM retezec privatniho klice (pro podpis PKP a XML Signature).
     */
    public function getPrivateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    /**
     * Vrati DER format certifikatu (binarni) pro x5t#S256 header.
     */
    public function getCertificateDer(): string
    {
        $temp = tmpfile();
        $tempPath = stream_get_meta_data($temp)['uri'];
        openssl_x509_export($this->parsedCertificate(), $tempContent, false);
        fclose($temp);

        $temp2 = tmpfile();
        $tempPath2 = stream_get_meta_data($temp2)['uri'];
        file_put_contents($tempPath2, $tempContent);

        return $tempContent;
    }

    /**
     * Vrati SHA-256 hash DER formatu certifikatu (base64url) pro x5t#S256 JWT header.
     */
    public function getCertificateThumbprintS256(): string
    {
        $der = $this->getCertificateAsDer();
        $hash = hash('sha256', $der, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Vrati informace o certifikatu.
     */
    public function getInfo(): CertificateInfo
    {
        $cert = $this->parsedCertificate();

        return new CertificateInfo(
            subject: $this->extractField($cert, 'subject'),
            issuer: $this->extractField($cert, 'issuer'),
            validFrom: $this->extractDate($cert, 'validFrom_time_t'),
            validTo: $this->extractDate($cert, 'validTo_time_t'),
            serialNumber: $this->extractField($cert, 'serialNumberHex'),
            fingerprint: $this->extractField($cert, 'fingerprint'),
        );
    }

    /**
     * Ověří, zda je certifikát platný.
     */
    public function isExpired(): bool
    {
        return $this->getInfo()->validTo < now();
    }

    /**
     * Vrati openssl pkey resource privatniho klice.
     */
    public function getOpenSslPrivateKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private($this->privateKeyPem);

        if ($key === false) {
            throw new CertificateException('Failed to load private key from certificate');
        }

        return $key;
    }

    /**
     * Vrati x509 resource certifikatu.
     */
    public function getOpenSslCertificate(): \OpenSSLCertificate
    {
        return $this->parsedCertificate();
    }

    /**
     * Vrati base64 PEM format celeho retezce certifikatu (pro WS-Security BinarySecurityToken).
     */
    public function getBinarySecurityToken(): string
    {
        $certContent = $this->getCertificatePem();

        return base64_encode($certContent);
    }

    // ─── Interni metody ────────────────────────────────────────────

    private function loadCertificate(): void
    {
        $p12Content = file_get_contents($this->p12Path);

        if ($p12Content === false) {
            throw new CertificateException("Cannot read certificate file: {$this->p12Path}");
        }

        $certs = [];
        $success = openssl_pkcs12_read($p12Content, $certs, $this->password);

        if (! $success || empty($certs['cert'])) {
            throw new CertificateException('Failed to parse PKCS#12 certificate. Invalid password or corrupted file.');
        }

        $this->parsedCert = $certs['cert'];
        $this->certificatePem = $certs['cert'];
        $this->privateKeyPem = $certs['pkey'] ?? throw new CertificateException('No private key found in PKCS#12');
    }

    private function parsedCertificate(): \OpenSSLCertificate
    {
        $cert = openssl_x509_read($this->certificatePem);

        if ($cert === false) {
            throw new CertificateException('Failed to parse X.509 certificate');
        }

        return $cert;
    }

    private function extractField(\OpenSSLCertificate $cert, string $field): string
    {
        $info = openssl_x509_parse($cert);
        $value = $info[$field] ?? '';

        // openssl_x509_parse vraci pole pro subject a issuer
        if (is_array($value)) {
            return implode(', ', array_map(
                fn ($k, $v) => "{$k}={$v}",
                array_keys($value),
                array_values($value),
            ));
        }

        return (string) $value;
    }

    private function extractDate(\OpenSSLCertificate $cert, string $field): \Illuminate\Support\Carbon
    {
        $info = openssl_x509_parse($cert);

        return \Illuminate\Support\Carbon::createFromTimestamp($info[$field] ?? 0);
    }

    /**
     * Export certifikatu do DER formatu (binarni, bez PEM hlavicky).
     */
    private function getCertificateAsDer(): string
    {
        $pem = $this->certificatePem;

        // Odstran PEM hlavicku a paticku
        $pem = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $pem);

        return base64_decode($pem, true);
    }
}
