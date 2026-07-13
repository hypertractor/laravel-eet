<?php

namespace Pomocnik\Eet\Tests\Unit;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\Certificates\CertificateInfo;

class CertificateInfoTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2027-12-31');

        $info = new CertificateInfo(
            subject: 'CN=CZ00000019, C=CZ',
            issuer: 'CN=EET CA',
            validFrom: $from,
            validTo: $to,
            serialNumber: 'ABC123',
            fingerprint: 'AA:BB:CC',
        );

        $this->assertSame('CN=CZ00000019, C=CZ', $info->subject);
        $this->assertSame('CN=EET CA', $info->issuer);
        $this->assertTrue($info->validFrom->eq($from));
        $this->assertTrue($info->validTo->eq($to));
        $this->assertSame('ABC123', $info->serialNumber);
        $this->assertSame('AA:BB:CC', $info->fingerprint);
    }

    public function test_is_expired_when_past(): void
    {
        $info = new CertificateInfo(
            subject: '', issuer: '',
            validFrom: Carbon::now()->subYear(),
            validTo: Carbon::now()->subDay(),
            serialNumber: '', fingerprint: '',
        );

        $this->assertTrue($info->isExpired());
    }

    public function test_is_expired_when_future(): void
    {
        $info = new CertificateInfo(
            subject: '', issuer: '',
            validFrom: Carbon::now()->subYear(),
            validTo: Carbon::now()->addYear(),
            serialNumber: '', fingerprint: '',
        );

        $this->assertFalse($info->isExpired());
    }

    public function test_days_until_expiry(): void
    {
        $info = new CertificateInfo(
            subject: '', issuer: '',
            validFrom: Carbon::now()->subYear(),
            validTo: Carbon::now()->addDays(30),
            serialNumber: '', fingerprint: '',
        );

        $this->assertEqualsWithDelta(30, $info->daysUntilExpiry(), 1);
    }

    public function test_days_until_expiry_expired(): void
    {
        $info = new CertificateInfo(
            subject: '', issuer: '',
            validFrom: Carbon::now()->subYear(),
            validTo: Carbon::now()->subDays(10),
            serialNumber: '', fingerprint: '',
        );

        $this->assertLessThan(0, $info->daysUntilExpiry());
    }

    public function test_to_array(): void
    {
        $from = Carbon::parse('2026-01-01T00:00:00Z');
        $to = Carbon::parse('2027-12-31T23:59:59Z');

        $info = new CertificateInfo(
            subject: 'CN=Test',
            issuer: 'CN=CA',
            validFrom: $from,
            validTo: $to,
            serialNumber: '123',
            fingerprint: 'AB:CD',
        );

        $array = $info->toArray();

        $this->assertSame('CN=Test', $array['subject']);
        $this->assertSame('CN=CA', $array['issuer']);
        $this->assertSame($from->toIso8601String(), $array['valid_from']);
        $this->assertSame($to->toIso8601String(), $array['valid_to']);
        $this->assertSame('123', $array['serial_number']);
        $this->assertSame('AB:CD', $array['fingerprint']);
    }
}
