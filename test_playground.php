<?php

/**
 * Testovaci script - odesle priklad do EET Playground BEZ Laravel.
 *
 * Pouziti:
 *   cd packages/eet-client && php test_playground.php
 */

require_once __DIR__.'/vendor/autoload.php';

use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Crypto\BkpGenerator;
use Pomocnik\Eet\Crypto\PkpGenerator;
use Pomocnik\Eet\Crypto\XmlSigner;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\Xml\XmlBuilder;

echo "=== EET 2.0 Playground Test (standalone) ===\n\n";

// 1. Nacist certifikat
$certPath = __DIR__.'/../../docs/CA_EET-Playground-CZ00000019.p12';
$certPass = 'aaaa1111';
$endpoint = 'https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4';

echo "1. Nacitavam certifikat...\n";

try {
    $certManager = new CertificateManager($certPath, $certPass);
    $info = $certManager->getInfo();
    echo "   Subject: {$info->subject}\n";
    echo "   Valid do: {$info->validTo->format('Y-m-d H:i:s')}\n";
    echo "   Vyprsi za: {$info->daysUntilExpiry()} dni\n";
} catch (Exception $e) {
    echo "   CHYBA: {$e->getMessage()}\n";
    exit(1);
}

// 2. Sestavit request
echo "\n2. Sestavuji EET request...\n";

$request = new EetRequest(
    eicPopl: 'CZ00000019',
    idJednotky: '303',
    idPokl: '/5604/MA65',
    poradCis: '00/2224/SO57',
    datTrzby: (new DateTimeImmutable())->format('Y-m-d\TH:i:s'),
    celkTrzba: '100.00',
    rezim: 0,
    prvniZaslani: true,
);

echo "   UUID: {$request->uuidZpravy}\n";
echo "   EIC: {$request->eicPopl}\n";
echo "   Castka: {$request->celkTrzba}\n";
echo "   Sign data: {$request->getSignData()}\n";

// 3. Generovat PKP/BKP
echo "\n3. Generuji PKP a BKP...\n";

$pkpGen = new PkpGenerator();
$bkpGen = new BkpGenerator();

$pkp = $pkpGen->generate($request, $certManager);
$bkp = $bkpGen->generate($pkp);

echo "   PKP: {$pkp}\n";
echo "   BKP: {$bkp}\n";

// 4. Sestavit a podepsat SOAP
echo "\n4. Sestavuji a podepisuji SOAP obalku...\n";

$xmlBuilder = new XmlBuilder();
$unsignedEnvelope = $xmlBuilder->buildSoapEnvelope($request);

echo "   Nepodepsana obalka: ".strlen($unsignedEnvelope)." bytu\n";

$signer = new XmlSigner();
$signedEnvelope = $signer->signSoapEnvelope($unsignedEnvelope, $certManager);

echo "   Podepsana obalka: ".strlen($signedEnvelope)." bytu\n";

// Ulozit pro debug
file_put_contents(__DIR__.'/tests/fixtures/last_request.xml', $signedEnvelope);
echo "   Ulozeno do tests/fixtures/last_request.xml\n";

// 5. Odeslat na Playground
echo "\n5. Odesilam na Playground...\n";
echo "   Endpoint: {$endpoint}\n";

$client = new \GuzzleHttp\Client([
    'timeout' => 30.0,
    'verify' => true,
]);

try {
    $startTime = microtime(true);
    $response = $client->post($endpoint, [
        'body' => $signedEnvelope,
        'headers' => [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'http://fs.gov.cz/eet/OdeslaniTrzby',
        ],
    ]);
    $elapsed = round((microtime(true) - $startTime) * 1000);

    $statusCode = $response->getStatusCode();
    $body = (string) $response->getBody();

    echo "   HTTP {$statusCode} ({$elapsed}ms)\n";
    echo "   Odpoved (".strlen($body)." bytu):\n";

    // Ulozit odpoved
    file_put_contents(__DIR__.'/tests/fixtures/last_response.xml', $body);

    // Parsovat odpoved
    $soapResponse = \Pomocnik\Eet\Soap\SoapResponse::fromXml($body);

    if ($soapResponse->success) {
        echo "\n   *** USPECH! ***\n";
        echo "   FIK: {$soapResponse->fikCode}\n";

        if ($soapResponse->testFikCode) {
            echo "   Test FIK: {$soapResponse->testFikCode}\n";
        }
    } else {
        echo "\n   *** CHYBA z EET ***\n";
        echo "   Kod chyby: {$soapResponse->errorCode}\n";
        echo "   Zprava: {$soapResponse->errorMessage}\n";
    }
} catch (\GuzzleHttp\Exception\ConnectException $e) {
    echo "   CHYBA pripojeni: {$e->getMessage()}\n";
} catch (\GuzzleHttp\Exception\RequestException $e) {
    echo "   CHYBA požadavku: {$e->getMessage()}\n";
    if ($e->hasResponse()) {
        echo "   HTTP: {$e->getResponse()->getStatusCode()}\n";
        echo "   Body: ".(string) $e->getResponse()->getBody()."\n";
    }
} catch (Exception $e) {
    echo "   VYJIMKA: {$e->getMessage()}\n";
}

echo "\n=== Hotovo ===\n";
