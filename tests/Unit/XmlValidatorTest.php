<?php

namespace Pomocnik\Eet\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Exceptions\XmlValidationException;
use Pomocnik\Eet\Xml\XmlBuilder;
use Pomocnik\Eet\Xml\XmlValidator;

class XmlValidatorTest extends TestCase
{
    private XmlValidator $validator;
    private XmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new XmlValidator();
        $this->builder = new XmlBuilder();
    }

    protected function getPackageProviders($app): array
    {
        return [\Pomocnik\Eet\EetClientServiceProvider::class];
    }

    public function test_validate_trzba_valid_xml(): void
    {
        $request = $this->makeTestRequest();
        $xml = $this->builder->buildTrzbaXml($request);

        $xsdPath = __DIR__.'/../../resources/schema/EETXMLSchema.xsd';

        if (! file_exists($xsdPath)) {
            $this->markTestSkipped('XSD schema not found, validator returns true by default');
        }

        $this->assertTrue($this->validator->validateTrzba($xml));
    }

    public function test_validate_trzba_invalid_xml_throws(): void
    {
        $xsdPath = __DIR__.'/../../resources/schema/EETXMLSchema.xsd';

        if (! file_exists($xsdPath)) {
            $this->markTestSkipped('XSD schema not found');
        }

        $this->expectException(XmlValidationException::class);

        $this->validator->validateTrzba('not valid xml at all');
    }

    public function test_validate_trzba_returns_true_when_no_xsd(): void
    {
        config(['eet.xsd_path' => '/nonexistent/path.xsd']);

        $validator = new XmlValidator();

        $this->assertTrue($validator->validateTrzba('<any>xml</any>'));
    }

    public function test_validate_odpoved_returns_true_when_no_xsd(): void
    {
        config(['eet.xsd_path' => '/nonexistent/path.xsd']);

        $validator = new XmlValidator();

        $this->assertTrue($validator->validateOdpoved('<any>xml</any>'));
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
}
