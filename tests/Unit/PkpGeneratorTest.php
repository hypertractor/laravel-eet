<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Crypto\PkpGenerator;
use Pomocnik\Eet\DTOs\EetRequest;

class PkpGeneratorTest extends TestCase
{
    private PkpGenerator $generator;
    private CertificateManager $certManager;

    protected function setUp(): void
    {
        parent::setUp();

        $certPath = __DIR__.'/../../../../docs/CA_EET-Playground-CZ00000019.p12';

        if (! file_exists($certPath)) {
            $this->markTestSkipped('Playground certificate not found');
        }

        $this->certManager = new CertificateManager($certPath, 'aaaa1111');
        $this->generator = new PkpGenerator();
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

    public function test_generate_returns_base64(): void
    {
        $request = $this->createTestRequest();
        $pkp = $this->generator->generate($request, $this->certManager);

        $this->assertNotEmpty($pkp);
        $decoded = base64_decode($pkp, true);
        $this->assertNotFalse($decoded);
        $this->assertGreaterThan(0, strlen($decoded));
    }

    public function test_generate_deterministic(): void
    {
        $request = $this->createTestRequest();

        $pkp1 = $this->generator->generate($request, $this->certManager);
        $pkp2 = $this->generator->generate($request, $this->certManager);

        $this->assertSame($pkp1, $pkp2);
    }

    public function test_generate_different_data_different_signature(): void
    {
        $request1 = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '303', idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57', datTrzby: '2026-07-01T09:02:18Z', celkTrzba: '100.00',
        );
        $request2 = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '303', idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57', datTrzby: '2026-07-01T09:02:18Z', celkTrzba: '200.00',
        );

        $pkp1 = $this->generator->generate($request1, $this->certManager);
        $pkp2 = $this->generator->generate($request2, $this->certManager);

        $this->assertNotSame($pkp1, $pkp2);
    }

    public function test_verify_valid_signature(): void
    {
        $request = $this->createTestRequest();
        $pkp = $this->generator->generate($request, $this->certManager);

        $this->assertTrue($this->generator->verify($request, $pkp, $this->certManager));
    }

    public function test_verify_invalid_signature(): void
    {
        $request = $this->createTestRequest();
        $fakePkp = base64_encode(random_bytes(256));

        $this->assertFalse($this->generator->verify($request, $fakePkp, $this->certManager));
    }

    public function test_verify_tampered_data(): void
    {
        $request = $this->createTestRequest();
        $pkp = $this->generator->generate($request, $this->certManager);

        $tamperedRequest = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '303', idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57', datTrzby: '2026-07-01T09:02:18Z', celkTrzba: '999.00',
        );

        $this->assertFalse($this->generator->verify($tamperedRequest, $pkp, $this->certManager));
    }

    public function test_sign_data_format_matches_spec(): void
    {
        $request = $this->createTestRequest();
        $pkp = $this->generator->generate($request, $this->certManager);

        $this->assertSame('CZ00000019|303|/5604/MA65|00/2224/SO57|2026-07-01T09:02:18Z|188580.00', $request->getSignData());
    }
}
