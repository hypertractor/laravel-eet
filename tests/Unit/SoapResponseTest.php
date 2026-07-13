<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Soap\SoapResponse;

class SoapResponseTest extends TestCase
{
    public function test_parse_success_response(): void
    {
        $xml = $this->getFixture('success_response.xml');
        $response = SoapResponse::fromXml($xml);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->fikCode);
        $this->assertNull($response->errorCode);
    }

    public function test_parse_error_response(): void
    {
        $xml = $this->getFixture('error_response.xml');
        $response = SoapResponse::fromXml($xml);

        $this->assertFalse($response->success);
        $this->assertNotNull($response->errorCode);
        $this->assertNotNull($response->errorMessage);
    }

    public function test_parse_test_mode_response(): void
    {
        $xml = $this->getFixture('test_response.xml');
        $response = SoapResponse::fromXml($xml);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->testFikCode);
    }

    private function getFixture(string $name): string
    {
        $path = __DIR__.'/../fixtures/'.$name;

        if (! file_exists($path)) {
            $this->markTestSkipped("Fixture {$name} not found");
        }

        return file_get_contents($path);
    }
}
