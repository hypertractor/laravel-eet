<?php

namespace Pomocnik\Eet\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pomocnik\Eet\Crypto\BkpGenerator;

class BkpGeneratorTest extends TestCase
{
    private BkpGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new BkpGenerator();
    }

    public function test_generate_returns_formatted_bkp(): void
    {
        // Znama hodnota PKP base64 - pro testovani deterministicky
        $pkpBase64 = base64_encode('test-data-to-hash');

        $bkp = $this->generator->generate($pkpBase64);

        // BKP format: XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX (32 hex chars + 4 pomlcky)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $bkp);
    }

    public function test_generate_deterministic(): void
    {
        $pkpBase64 = base64_encode('deterministic-test');

        $bkp1 = $this->generator->generate($pkpBase64);
        $bkp2 = $this->generator->generate($pkpBase64);

        $this->assertSame($bkp1, $bkp2);
    }

    public function test_generate_different_inputs_different_outputs(): void
    {
        $bkp1 = $this->generator->generate(base64_encode('input-1'));
        $bkp2 = $this->generator->generate(base64_encode('input-2'));

        $this->assertNotSame($bkp1, $bkp2);
    }
}
