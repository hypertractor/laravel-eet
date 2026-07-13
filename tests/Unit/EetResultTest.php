<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\DTOs\EetResult;

class EetResultTest extends TestCase
{
    public function test_success_factory(): void
    {
        $result = EetResult::success(
            fikCode: 'ac51eb11-1f89-4c49-8b2f-986a62bc0ba2-ff',
            testFikCode: 'ac51eb11-1f89-4c49-8b2f-986a62bc0ba2-ff',
            bkpCode: '2d02fd8d-5fa6-9665-c3fc-92be2476a695',
            pkpCode: 'base64pkp',
            uuidZpravy: '123e4567-e89b-12d3-a456-426614174000',
        );

        $this->assertTrue($result->success);
        $this->assertSame('ac51eb11-1f89-4c49-8b2f-986a62bc0ba2-ff', $result->fikCode);
        $this->assertSame('ac51eb11-1f89-4c49-8b2f-986a62bc0ba2-ff', $result->testFikCode);
        $this->assertSame('2d02fd8d-5fa6-9665-c3fc-92be2476a695', $result->bkpCode);
        $this->assertSame('base64pkp', $result->pkpCode);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->uuidZpravy);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }

    public function test_failure_factory(): void
    {
        $result = EetResult::failure(
            errorCode: 4,
            errorMessage: 'Neplatny podpis SOAP zpravy',
            uuidZpravy: '123e4567-e89b-12d3-a456-426614174000',
        );

        $this->assertFalse($result->success);
        $this->assertSame(4, $result->errorCode);
        $this->assertSame('Neplatny podpis SOAP zpravy', $result->errorMessage);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->uuidZpravy);
        $this->assertNull($result->fikCode);
        $this->assertNull($result->bkpCode);
    }

    public function test_failure_factory_with_raw_response(): void
    {
        $rawXml = '<soapenv:Envelope>...</soapenv:Envelope>';

        $result = EetResult::failure(
            errorCode: 3,
            errorMessage: 'Neplatna hodnota',
            uuidZpravy: null,
            rawResponse: $rawXml,
        );

        $this->assertSame($rawXml, $result->rawResponse);
    }

    public function test_constructor_defaults(): void
    {
        $result = new EetResult(success: false);

        $this->assertFalse($result->success);
        $this->assertNull($result->fikCode);
        $this->assertNull($result->bkpCode);
        $this->assertNull($result->pkpCode);
        $this->assertNull($result->testFikCode);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->warningCode);
        $this->assertNull($result->rawResponse);
        $this->assertNull($result->uuidZpravy);
    }

    public function test_success_with_null_test_fik(): void
    {
        $result = EetResult::success(
            fikCode: 'abc-123',
            testFikCode: null,
            bkpCode: 'bkp',
            pkpCode: 'pkp',
            uuidZpravy: 'uuid',
        );

        $this->assertTrue($result->success);
        $this->assertNull($result->testFikCode);
    }
}
