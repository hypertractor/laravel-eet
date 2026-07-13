<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\Certificates\CertificateInfo;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Exceptions\CertificateException;

class CertificateManagerTest extends TestCase
{
    private static string $certPath = __DIR__.'/../../../../docs/CA_EET-Playground-CZ00000019.p12';
    private static string $certPassword = 'aaaa1111';

    private function createManager(): CertificateManager
    {
        return new CertificateManager(self::$certPath, self::$certPassword);
    }

    public function test_loads_playground_certificate(): void
    {
        $manager = $this->createManager();

        $this->assertNotEmpty($manager->getCertificatePem());
        $this->assertStringContainsString('BEGIN CERTIFICATE', $manager->getCertificatePem());
    }

    public function test_loads_private_key(): void
    {
        $manager = $this->createManager();

        $this->assertNotEmpty($manager->getPrivateKeyPem());
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $manager->getPrivateKeyPem());
    }

    public function test_get_info_returns_certificate_info(): void
    {
        $manager = $this->createManager();
        $info = $manager->getInfo();

        $this->assertInstanceOf(CertificateInfo::class, $info);
        $this->assertStringContainsString('CZ00000019', $info->subject);
        $this->assertStringContainsString('CZ', $info->subject);
    }

    public function test_get_info_dates_are_valid(): void
    {
        $manager = $this->createManager();
        $info = $manager->getInfo();

        $this->assertTrue($info->validFrom->isPast());
        $this->assertTrue($info->validTo->isFuture());
    }

    public function test_not_expired(): void
    {
        $manager = $this->createManager();

        $this->assertFalse($manager->isExpired());
    }

    public function test_get_certificate_der(): void
    {
        $manager = $this->createManager();
        $der = $manager->getCertificateDer();

        $this->assertNotEmpty($der);
    }

    public function test_get_binary_security_token(): void
    {
        $manager = $this->createManager();
        $token = $manager->getBinarySecurityToken();

        $this->assertNotEmpty($token);
        $this->assertSame($manager->getCertificatePem(), base64_decode($token, true));
    }

    public function test_get_openssl_private_key(): void
    {
        $manager = $this->createManager();
        $key = $manager->getOpenSslPrivateKey();

        $this->assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
    }

    public function test_get_openssl_certificate(): void
    {
        $manager = $this->createManager();
        $cert = $manager->getOpenSslCertificate();

        $this->assertTrue(is_resource($cert) || $cert instanceof \OpenSSLCertificate);
    }

    public function test_invalid_path_throws_exception(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('Certificate file not found');

        new CertificateManager('/nonexistent/path.p12', 'password');
    }

    public function test_invalid_password_throws_exception(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('Failed to parse PKCS#12');

        new CertificateManager(self::$certPath, 'wrong_password');
    }

    public function test_get_certificate_thumbprint_s256(): void
    {
        $manager = $this->createManager();
        $thumbprint = $manager->getCertificateThumbprintS256();

        $this->assertNotEmpty($thumbprint);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $thumbprint);
    }

    public function test_certificate_pem_is_deterministic(): void
    {
        $manager1 = $this->createManager();
        $manager2 = $this->createManager();

        $this->assertSame($manager1->getCertificatePem(), $manager2->getCertificatePem());
    }
}
