<?php

namespace Pomocnik\Eet\Crypto;

class BkpGenerator
{
    /**
     * Vygeneruje Bezpecnostni Kod Poplatnika (BKP).
     *
     * BKP = SHA-256 hash PKP (base64 decoded), vysledek zakodovany jako hex string.
     * Format: XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
     */
    public function generate(string $pkpBase64): string
    {
        $pkpBinary = base64_decode($pkpBase64, true);
        $hash = hash('sha256', $pkpBinary);

        return $this->formatBkp($hash);
    }

    /**
     * Formatuje hex string do tvaru BKP: XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
     */
    private function formatBkp(string $hex): string
    {
        $hex = strtolower($hex);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
