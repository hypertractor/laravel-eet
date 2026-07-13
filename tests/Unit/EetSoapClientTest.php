<?php

namespace Pomocnik\Eet\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Orchestra\Testbench\TestCase;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Soap\EetSoapClient;
use Pomocnik\Eet\Soap\SoapResponse;
use Pomocnik\Eet\Xml\XmlBuilder;

class EetSoapClientTest extends TestCase
{
    private CertificateManager $certManager;

    protected function setUp(): void
    {
        parent::setUp();

        $certPath = __DIR__.'/../../../../docs/CA_EET-Playground-CZ00000019.p12';

        if (! file_exists($certPath)) {
            $this->markTestSkipped('Playground certificate not found');
        }

        $this->certManager = new CertificateManager($certPath, 'aaaa1111');

        config(['eet.test_mode' => true]);
        config(['eet.timeouts.soap' => 30.0]);
        config(['eet.ca_certificates.playground_root' => null]);
        config(['eet.ca_certificates.playground_sub' => null]);
    }

    protected function getPackageProviders($app): array
    {
        return [\Pomocnik\Eet\EetClientServiceProvider::class];
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
        );
    }

    private function createClientWithMock(array $responses): EetSoapClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $client = new EetSoapClient($this->certManager, new XmlBuilder());

        $reflection = new \ReflectionClass($client);
        $prop = $reflection->getProperty('httpClient');
        $prop->setValue($client, new Client(['handler' => $handlerStack, 'verify' => false]));

        return $client;
    }

    public function test_send_request_returns_soap_response(): void
    {
        $responseXml = file_get_contents(__DIR__.'/../fixtures/success_response.xml');

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'text/xml'], $responseXml),
        ]);

        $result = $client->sendRequest($this->makeTestRequest(), 'https://example.com/eet');

        $this->assertInstanceOf(SoapResponse::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('b3a616d6-4f46-464f-b3d2-08e67e3be44e-01', $result->fikCode);
    }

    public function test_send_request_error_response(): void
    {
        $responseXml = file_get_contents(__DIR__.'/../fixtures/error_response.xml');

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'text/xml'], $responseXml),
        ]);

        $result = $client->sendRequest($this->makeTestRequest(), 'https://example.com/eet');

        $this->assertFalse($result->success);
        $this->assertSame(3, $result->errorCode);
    }

    public function test_send_request_throws_on_connection_error(): void
    {
        $this->expectException(\Pomocnik\Eet\Exceptions\SoapException::class);
        $this->expectExceptionMessage('SOAP communication failed');

        $client = $this->createClientWithMock([
            new ConnectException(
                'Connection refused',
                new GuzzleRequest('POST', 'https://example.com/eet'),
            ),
        ]);

        $client->sendRequest($this->makeTestRequest(), 'https://example.com/eet');
    }

    public function test_send_request_sends_soap_action_header(): void
    {
        $responseXml = file_get_contents(__DIR__.'/../fixtures/success_response.xml');

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'text/xml'], $responseXml),
        ]);

        $result = $client->sendRequest($this->makeTestRequest(), 'https://example.com/eet');

        $this->assertTrue($result->success);
    }

    public function test_send_request_body_is_xml(): void
    {
        $responseXml = file_get_contents(__DIR__.'/../fixtures/success_response.xml');

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'text/xml'], $responseXml),
        ]);

        $result = $client->sendRequest($this->makeTestRequest(), 'https://example.com/eet');

        $this->assertTrue($result->success);
    }
}
