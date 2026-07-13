<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Xml\XmlBuilder;

class XmlBuilderTest extends TestCase
{
    private XmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new XmlBuilder();
    }

    public function test_build_trzba_xml_contains_required_elements(): void
    {
        $request = $this->createTestRequest();

        $xml = $this->builder->buildTrzbaXml($request);

        $this->assertStringContainsString('v4:Trzba', $xml);
        $this->assertStringContainsString('v4:Hlavicka', $xml);
        $this->assertStringContainsString('v4:Data', $xml);
        $this->assertStringContainsString('uuid_zpravy', $xml);
        $this->assertStringContainsString('dat_odesl', $xml);
        $this->assertStringContainsString('prvni_zaslani', $xml);
    }

    public function test_build_trzba_xml_contains_data_attributes(): void
    {
        $request = $this->createTestRequest();

        $xml = $this->builder->buildTrzbaXml($request);

        $this->assertStringContainsString('eic_popl="CZ00000019"', $xml);
        $this->assertStringContainsString('id_jednotky="303"', $xml);
        $this->assertStringContainsString('id_pokl="/5604/MA65"', $xml);
        $this->assertStringContainsString('porad_cis="00/2224/SO57"', $xml);
        $this->assertStringContainsString('celk_trzba="188580.00"', $xml);
    }

    public function test_build_trzba_xml_datetime_format(): void
    {
        $request = $this->createTestRequest();

        $xml = $this->builder->buildTrzbaXml($request);

        // Datum musi byt ve formatu YYYY-MM-DDTHH:MM:SSZ nebo s offsetem
        $this->assertMatchesRegularExpression('/dat_trzby="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $xml);
    }

    public function test_build_soap_envelope_structure(): void
    {
        $request = $this->createTestRequest();

        $xml = $this->builder->buildSoapEnvelope($request);

        $this->assertStringContainsString('soapenv:Envelope', $xml);
        $this->assertStringContainsString('soapenv:Header', $xml);
        $this->assertStringContainsString('soapenv:Body', $xml);
        $this->assertStringContainsString('http://schemas.xmlsoap.org/soap/envelope/', $xml);
        $this->assertStringContainsString('http://fs.gov.cz/eet/schema/v4', $xml);
    }

    public function test_build_trzba_xml_valid_xml(): void
    {
        $request = $this->createTestRequest();

        $xml = $this->builder->buildTrzbaXml($request);

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
    }

    public function test_build_trzba_xml_with_optional_fields(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
            urcenoCerpZuct: '25.00',
            cerpZuct: '302.00',
        );

        $xml = $this->builder->buildTrzbaXml($request);

        $this->assertStringContainsString('urceno_cerp_zuct="25.00"', $xml);
        $this->assertStringContainsString('cerp_zuct="302.00"', $xml);
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
}
