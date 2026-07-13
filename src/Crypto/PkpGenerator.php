<?php

namespace Pomocnik\Eet\Crypto;

use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Exceptions\EetException;
use Pomocnik\Eet\Certificates\CertificateManager;

class PkpGenerator
{
    /**
     * Vygeneruje Podpisovy Kod Poplatnika (PKP).
     *
     * PKP = RSA SHA-256 podpis retezce: eic_popl|id_jednotky|id_pokl|porad_cis|dat_trzby|celk_trzba
     *
     * Vysledek je base64 encoded podpis.
     */
    public function generate(EetRequest $request, CertificateManager $certManager): string
    {
        $dataToSign = $request->getSignData();

        $signature = '';

        $success = openssl_sign(
            $dataToSign,
            $signature,
            $certManager->getOpenSslPrivateKey(),
            OPENSSL_ALGO_SHA256
        );

        if (! $success) {
            throw new EetException('Failed to generate PKP signature');
        }

        return base64_encode($signature);
    }

    /**
     * Overi PKP podpis.
     */
    public function verify(EetRequest $request, string $pkpBase64, CertificateManager $certManager): bool
    {
        $dataToVerify = $request->getSignData();
        $signature = base64_decode($pkpBase64, true);

        return openssl_verify(
            $dataToVerify,
            $signature,
            $certManager->getCertificatePem(),
            OPENSSL_ALGO_SHA256
        ) === 1;
    }
}
