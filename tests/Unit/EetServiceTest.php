<?php

namespace Pomocnik\Eet\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Crypto\BkpGenerator;
use Pomocnik\Eet\Crypto\PkpGenerator;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Services\EetService;
use Pomocnik\Eet\Soap\EetSoapClient;

class EetServiceTest extends TestCase
{
    private CertificateManager $certManager;
    private EetSoapClient $soapClient;
    private PkpGenerator $pkpGenerator;
    private BkpGenerator $bkpGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $certPath = __DIR__.'/../../../../docs/CA_EET-Playground-CZ00000019.p12';

        if (! file_exists($certPath)) {
            $this->markTestSkipped('Playground certificate not found');
        }

        $this->certManager = new CertificateManager($certPath, 'aaaa1111');
        $this->soapClient = $this->createMock(EetSoapClient::class);
        $this->pkpGenerator = new PkpGenerator();
        $this->bkpGenerator = new BkpGenerator();

        config(['eet.test_mode' => true]);
        config(['eet.endpoint_playground' => 'https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4']);
        config(['eet.endpoint_production' => 'https://trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4']);
        config(['eet.unit_id' => '1']);
        config(['eet.terminal_id' => '1']);
    }

    protected function getPackageProviders($app): array
    {
        return [\Pomocnik\Eet\EetClientServiceProvider::class];
    }

    private function createService(?EetSoapClient $soapClient = null): EetService
    {
        return new EetService(
            $this->certManager,
            $soapClient ?? $this->soapClient,
            $this->pkpGenerator,
            $this->bkpGenerator,
        );
    }

    private function makeTestRequest(): EetRequest
    {
        return new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
            uuidZpravy: '123e4567-e89b-12d3-a456-426614174000',
        );
    }

    public function test_submit_success(): void
    {
        $response = new \Pomocnik\Eet\Soap\SoapResponse(
            success: true,
            fikCode: 'b3a616d6-4f46-464f-b3d2-08e67e3be44e-01',
            testFikCode: 'b3a616d6-4f46-464f-b3d2-08e67e3be44e-01',
        );

        $this->soapClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $service = $this->createService();
        $result = $service->submit($this->makeTestRequest());

        $this->assertTrue($result->success);
        $this->assertSame('b3a616d6-4f46-464f-b3d2-08e67e3be44e-01', $result->fikCode);
        $this->assertNotNull($result->pkpCode);
        $this->assertNotNull($result->bkpCode);
    }

    public function test_submit_failure(): void
    {
        $response = new \Pomocnik\Eet\Soap\SoapResponse(
            success: false,
            errorCode: 3,
            errorMessage: 'Neplatna hodnota',
        );

        $this->soapClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $service = $this->createService();
        $result = $service->submit($this->makeTestRequest());

        $this->assertFalse($result->success);
        $this->assertSame(3, $result->errorCode);
        $this->assertSame('Neplatna hodnota', $result->errorMessage);
    }

    public function test_submit_soap_exception_returns_failure(): void
    {
        $this->soapClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new \Pomocnik\Eet\Exceptions\EetException('Connection timeout'));

        $service = $this->createService();
        $result = $service->submit($this->makeTestRequest());

        $this->assertFalse($result->success);
        $this->assertSame(-1, $result->errorCode);
        $this->assertSame('Connection timeout', $result->errorMessage);
    }

    public function test_submit_success_includes_bkp_and_pkp(): void
    {
        $response = new \Pomocnik\Eet\Soap\SoapResponse(
            success: true,
            fikCode: 'fik-code-123',
        );

        $this->soapClient
            ->method('sendRequest')
            ->willReturn($response);

        $service = $this->createService();
        $result = $service->submit($this->makeTestRequest());

        $this->assertNotEmpty($result->pkpCode);
        $this->assertNotEmpty($result->bkpCode);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $result->bkpCode);
    }

    public function test_submit_preserves_uuid(): void
    {
        $response = new \Pomocnik\Eet\Soap\SoapResponse(success: true, fikCode: 'fik');

        $this->soapClient->method('sendRequest')->willReturn($response);

        $request = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '303', idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57', datTrzby: '2026-07-01T09:02:18Z', celkTrzba: '100.00',
            uuidZpravy: 'my-custom-uuid',
        );

        $service = $this->createService();
        $result = $service->submit($request);

        $this->assertSame('my-custom-uuid', $result->uuidZpravy);
    }

    public function test_get_endpoint_playground(): void
    {
        config(['eet.test_mode' => true]);

        $service = $this->createService();

        $endpoint = $service->getEndpoint();

        $this->assertStringContainsString('pg.trzbyeet.gov.cz', $endpoint);
    }

    public function test_get_endpoint_production(): void
    {
        config(['eet.test_mode' => false]);

        $service = $this->createService();

        $endpoint = $service->getEndpoint();

        $this->assertStringContainsString('trzbyeet.gov.cz', $endpoint);
        $this->assertStringNotContainsString('pg.', $endpoint);
    }

    public function test_create_request_from_receipt(): void
    {
        config(['eet.unit_id' => '303', 'eet.terminal_id' => '/5604/MA65']);

        $service = $this->createService();

        $request = $service->createRequestFromReceipt([
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'celk_trzba' => '188580.00',
        ]);

        $this->assertInstanceOf(EetRequest::class, $request);
        $this->assertSame('303', $request->idJednotky);
        $this->assertSame('/5604/MA65', $request->idPokl);
    }
}
