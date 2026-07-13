<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\DTOs\EetRequest;

class EetRequestTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
        );

        $this->assertSame('CZ00000019', $request->eicPopl);
        $this->assertSame('303', $request->idJednotky);
        $this->assertSame('/5604/MA65', $request->idPokl);
        $this->assertSame('00/2224/SO57', $request->poradCis);
        $this->assertSame('2026-07-01T09:02:18Z', $request->datTrzby);
        $this->assertSame('188580.00', $request->celkTrzba);
    }

    public function test_constructor_defaults(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '100.00',
        );

        $this->assertSame(0, $request->rezim);
        $this->assertTrue($request->prvniZaslani);
        $this->assertNull($request->uuidZpravy);
        $this->assertFalse($request->overeni);
        $this->assertNull($request->urcenoCerpZuct);
        $this->assertNull($request->cerpZuct);
        $this->assertNull($request->eicPoverujiciho);
        $this->assertNull($request->povereniVicePopl);
    }

    public function test_constructor_with_all_fields(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
            rezim: 1,
            prvniZaslani: false,
            uuidZpravy: '123e4567-e89b-12d3-a456-426614174000',
            overeni: true,
            urcenoCerpZuct: '25.00',
            cerpZuct: '302.00',
            eicPoverujiciho: 'CZ12345678',
            povereniVicePopl: true,
        );

        $this->assertSame(1, $request->rezim);
        $this->assertFalse($request->prvniZaslani);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $request->uuidZpravy);
        $this->assertTrue($request->overeni);
        $this->assertSame('25.00', $request->urcenoCerpZuct);
        $this->assertSame('302.00', $request->cerpZuct);
        $this->assertSame('CZ12345678', $request->eicPoverujiciho);
        $this->assertTrue($request->povereniVicePopl);
    }

    public function test_get_sign_data(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
        );

        $expected = 'CZ00000019|303|/5604/MA65|00/2224/SO57|2026-07-01T09:02:18Z|188580.00';
        $this->assertSame($expected, $request->getSignData());
    }

    public function test_get_sign_data_order_matters(): void
    {
        $request1 = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '1', idPokl: 'A', poradCis: '1',
            datTrzby: '2026-01-01T00:00:00Z', celkTrzba: '100.00',
        );
        $request2 = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '1', idPokl: 'A', poradCis: '1',
            datTrzby: '2026-01-01T00:00:00Z', celkTrzba: '200.00',
        );

        $this->assertNotSame($request1->getSignData(), $request2->getSignData());
    }

    public function test_to_array(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019',
            idJednotky: '303',
            idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57',
            datTrzby: '2026-07-01T09:02:18Z',
            celkTrzba: '188580.00',
        );

        $array = $request->toArray();

        $this->assertSame('CZ00000019', $array['eic_popl']);
        $this->assertSame('303', $array['id_jednotky']);
        $this->assertSame('/5604/MA65', $array['id_pokl']);
        $this->assertSame('00/2224/SO57', $array['porad_cis']);
        $this->assertSame('2026-07-01T09:02:18Z', $array['dat_trzby']);
        $this->assertSame('188580.00', $array['celk_trzba']);
        $this->assertSame(0, $array['rezim']);
        $this->assertTrue($array['prvni_zaslani']);
        $this->assertNull($array['uuid_zpravy']);
        $this->assertFalse($array['overeni']);
    }

    public function test_to_array_with_optional_fields(): void
    {
        $request = new EetRequest(
            eicPopl: 'CZ00000019', idJednotky: '303', idPokl: '/5604/MA65',
            poradCis: '00/2224/SO57', datTrzby: '2026-07-01T09:02:18Z', celkTrzba: '100.00',
            urcenoCerpZuct: '25.00', cerpZuct: '302.00',
            eicPoverujiciho: 'CZ12345678', povereniVicePopl: true,
        );

        $array = $request->toArray();

        $this->assertSame('25.00', $array['urceno_cerp_zuct']);
        $this->assertSame('302.00', $array['cerp_zuct']);
        $this->assertSame('CZ12345678', $array['eic_poverujiciho']);
        $this->assertTrue($array['povereni_vice_popl']);
    }

    public function test_from_receipt(): void
    {
        $data = [
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'dat_trzby' => '2026-07-01T09:02:18Z',
            'celk_trzba' => '188580.00',
        ];

        $request = EetRequest::fromReceipt($data, unitId: '303', terminalId: '/5604/MA65');

        $this->assertSame('CZ00000019', $request->eicPopl);
        $this->assertSame('303', $request->idJednotky);
        $this->assertSame('/5604/MA65', $request->idPokl);
        $this->assertSame('00/2224/SO57', $request->poradCis);
        $this->assertSame('2026-07-01T09:02:18Z', $request->datTrzby);
        $this->assertSame('188580.00', $request->celkTrzba);
    }

    public function test_from_receipt_generates_uuid_when_missing(): void
    {
        $data = [
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'celk_trzba' => '100.00',
        ];

        $request = EetRequest::fromReceipt($data, unitId: '1', terminalId: '1');

        $this->assertNotNull($request->uuidZpravy);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $request->uuidZpravy);
    }

    public function test_from_receipt_formats_amount(): void
    {
        $data = [
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'celk_trzba' => 100,
        ];

        $request = EetRequest::fromReceipt($data, unitId: '1', terminalId: '1');

        $this->assertSame('100.00', $request->celkTrzba);
    }

    public function test_from_receipt_formats_optional_amounts(): void
    {
        $data = [
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'celk_trzba' => '100.00',
            'urceno_cerp_zuct' => 25,
            'cerp_zuct' => 302.5,
        ];

        $request = EetRequest::fromReceipt($data, unitId: '1', terminalId: '1');

        $this->assertSame('25.00', $request->urcenoCerpZuct);
        $this->assertSame('302.50', $request->cerpZuct);
    }

    public function test_from_receipt_sets_defaults(): void
    {
        $data = [
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'celk_trzba' => '100.00',
        ];

        $request = EetRequest::fromReceipt($data, unitId: '1', terminalId: '1');

        $this->assertSame(0, $request->rezim);
        $this->assertTrue($request->prvniZaslani);
        $this->assertFalse($request->overeni);
    }

    public function test_from_receipt_overrides_defaults(): void
    {
        $data = [
            'eic_popl' => 'CZ00000019',
            'porad_cis' => '00/2224/SO57',
            'celk_trzba' => '100.00',
            'rezim' => 1,
            'prvni_zaslani' => false,
            'overeni' => true,
        ];

        $request = EetRequest::fromReceipt($data, unitId: '1', terminalId: '1');

        $this->assertSame(1, $request->rezim);
        $this->assertFalse($request->prvniZaslani);
        $this->assertTrue($request->overeni);
    }
}
