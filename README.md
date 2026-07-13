# Laravel EET

Laravel package for Czech Electronic Sales Registration (EET 2.0) — SOAP signing, PKP/BKP generation, certificate management, and receipt submission to the Financni sprava endpoint.

## Requirements

- PHP 8.2+
- Laravel 12+
- PHP `openssl` extension (for certificate handling and signing)
- PHP `dom` extension (for XML building and signing)

## Installation

```bash
composer require hypertractor/laravel-eet
```

Publish the config and bundleed playground certificates:

```bash
php artisan vendor:publish --tag=eet-config
php artisan vendor:publish --tag=eet-certs
```

Run the migrations to add EET fields to your `receipts` table and create the `eet_submissions` audit table:

```bash
php artisan vendor:publish --tag=eet-migrations
php artisan migrate
```

## Configuration

The `.env` file:

```env
EET_TEST_MODE=true
EET_UNIT_ID=1
EET_TERMINAL_ID=1
EET_CERTIFICATE_PATH=storage/eet/certs/CA_EET-Playground-CZ00000019.p12
EET_CERTIFICATE_PASSWORD=aaaa1111
```

For production, swap to your real certificate:

```env
EET_TEST_MODE=false
EET_CERTIFICATE_PATH=/path/to/your-production.p12
EET_CERTIFICATE_PASSWORD=your-password
```

Full config reference (`config/eet.php`):

| Key | Env Variable | Default |
|-----|-------------|---------|
| `endpoint_playground` | `EET_ENDPOINT_PLAYGROUND` | `https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4` |
| `endpoint_production` | `EET_ENDPOINT_PRODUCTION` | `https://trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4` |
| `test_mode` | `EET_TEST_MODE` | `true` |
| `unit_id` | `EET_UNIT_ID` | `1` |
| `terminal_id` | `EET_TERMINAL_ID` | `1` |
| `certificate.path` | `EET_CERTIFICATE_PATH` | bundled playground cert |
| `certificate.password` | `EET_CERTIFICATE_PASSWORD` | `aaaa1111` |
| `jwt_renewal.enabled` | `EET_JWT_RENEWAL_ENABLED` | `false` |
| `jwt_renewal.renew_days_before_expiry` | `EET_RENEW_DAYS_BEFORE` | `14` |
| `timeouts.soap` | `EET_SOAP_TIMEOUT` | `30.0` |
| `retries.max_attempts` | `EET_RETRY_MAX_ATTEMPTS` | `3` |
| `validate_xml` | `EET_VALIDATE_XML` | `true` |

## Quick Start

### Using the Facade

```php
use Pomocnik\Eet\Facades\Eet;
use Pomocnik\Eet\DTOs\EetRequest;

$request = new EetRequest(
    eicPopl: 'CZ1234567890',
    idJednotky: '1',
    idPokl: '1',
    poradCis: '1',
    datTrzby: '2026-07-13T10:00:00+02:00',
    celkTrzba: '100.00',
);

$result = Eet::submit($request);

if ($result->success) {
    echo $result->fikCode; // e.g. "ac51eb11-1f89-4c49-8b2f-986a62bc0ba2-ff"
}
```

### Using the Service directly

```php
use Pomocnik\Eet\Services\EetService;

$eet = app(EetService::class);

$request = $eet->createRequestFromReceipt([
    'eic_popl' => 'CZ1234567890',
    'porad_cis' => '1',
    'celk_trzba' => 100.00,
    'dat_trzby' => now()->format('Y-m-d\TH:i:s'),
]);

$result = $eet->submit($request);
```

## EetRequest DTO

Immutable value object representing an EET submission.

### Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `eicPopl` | `string` | yes | EIC of the payer (`CZ` + 9-10 digits) |
| `idJednotky` | `string` | yes | Evidence unit ID |
| `idPokl` | `string` | yes | Cash register / terminal ID |
| `poradCis` | `string` | yes | Receipt sequence number |
| `datTrzby` | `string` | yes | Transaction date (ISO 8601) |
| `celkTrzba` | `string` | yes | Total amount (2 decimal places) |
| `rezim` | `int` | no | 0 = bunch mode (default), 1 = online |
| `prvniZaslani` | `bool` | no | First submission (default `true`) |
| `uuidZpravy` | `?string` | no | Message UUID (auto-generated if null) |
| `overeni` | `bool` | no | Test mode flag |
| `urcenoCerpZuct` | `?string` | no | Amount intended for withdrawal |
| `cerpZuct` | `?string` | no | Amount withdrawn |
| `eicPoverujiciho` | `?string` | no | EIC of the authorizing entity |
| `povereniVicePopl` | `?bool` | no | Authorization for multiple payers |

### Factory method

```php
$request = EetRequest::fromReceipt(
    data: $receipt->toArray(),
    unitId: config('eet.unit_id'),
    terminalId: config('eet.terminal_id'),
);
```

Expects array keys: `eic_popl`, `porad_cis`, `celk_trzba`, and optionally `dat_trzby`, `rezim`, `prvni_zaslani`, `uuid_zpravy`, `overeni`.

## EetResult DTO

Immutable value object returned by `EetService::submit()`.

| Property | Type | Description |
|----------|------|-------------|
| `success` | `bool` | Whether the submission succeeded |
| `fikCode` | `?string` | Fiscal identification code from EET (on success) |
| `bkpCode` | `?string` | Security code (SHA-256 of PKP) |
| `pkpCode` | `?string` | Signature code (RSA-SHA-256, base64) |
| `testFikCode` | `?string` | Test FIK code (when in test mode) |
| `errorCode` | `?int` | Error code from EET server |
| `errorMessage` | `?string` | Human-readable error message |
| `uuidZpravy` | `?string` | Message UUID |
| `rawResponse` | `?string` | Raw XML response |

## HasEetFields Trait

Add this trait to your `Receipt` model to get EET columns, accessors, scopes, and auto-defaults.

```php
use Pomocnik\Eet\Models\Concerns\HasEetFields;

class Receipt extends Model
{
    use HasEetFields;
}
```

### Columns added to `receipts` table

| Column | Type | Description |
|--------|------|-------------|
| `fik_code` | `VARCHAR(64)` null | FIK code on success |
| `bkp_code` | `VARCHAR(64)` null | BKP code |
| `pkp_code` | `TEXT` null | PKP code (base64 signature) |
| `eet_status` | `ENUM('pending','sent','failed','cancelled')` | Current status |
| `eet_submitted_at` | `TIMESTAMP` null | When submitted successfully |
| `eet_first_send` | `BOOLEAN` | First submission flag |
| `eet_test_mode` | `BOOLEAN` | Was submitted in test mode |
| `eet_uuid` | `CHAR(36)` null | Message UUID |

### Auto-defaults

On `creating`, the trait sets:
- `eet_status` = `'pending'`
- `eet_first_send` = `true`
- `eet_test_mode` = current `config('eet.test_mode')`

### Accessors

```php
$receipt->is_eet_registered; // true when status === 'sent'
$receipt->is_eet_pending;    // true when status === 'pending'
$receipt->is_eet_failed;     // true when status === 'failed'
```

### Query scopes

```php
Receipt::eetPending()->get();
Receipt::eetSent()->get();
Receipt::eetFailed()->get();
Receipt::eetMandatory()->get(); // receipts where paymentType->eet_mandatory = true
```

## EetSubmission Model & Audit Trail

Every submission is logged in the `eet_submissions` table with full request/response XML. The `EetSubmission` model provides a `receipt()` relationship.

```php
use Pomocnik\Eet\Models\EetSubmission;

$submissions = EetSubmission::where('receipt_id', $receipt->id)
    ->orderByDesc('created_at')
    ->get();

$submissions->first()->request_xml;   // full SOAP request
$submissions->first()->response_xml;  // full SOAP response
$submissions->first()->fik_code;
$submissions->first()->error_code;
```

## Events

The package dispatches events on submission result. Listen for them in your `EventServiceProvider`:

```php
use Pomocnik\Eet\Events\EetSubmissionSucceeded;
use Pomocnik\Eet\Events\EetSubmissionFailed;

protected $listen = [
    EetSubmissionSucceeded::class => [
        UpdateReceiptEetFields::class,
    ],
    EetSubmissionFailed::class => [
        LogEetFailure::class,
    ],
];
```

Both events expose `public readonly EetResult $result`.

## Certificate Management

### Checking certificate status

```php
use Pomocnik\Eet\Certificates\CertificateManager;

$cm = app(CertificateManager::class);
$info = $cm->getInfo();

echo $info->subject;          // "CN=CZ00000019, ..."
echo $info->validTo->format('Y-m-d'); // "2027-05-14"
echo $info->daysUntilExpiry(); // 304
echo $info->isExpired();       // false
```

### Bundled playground certificates

The package ships with 3 playground `.p12` files for testing:

- `CA_EET-Playground-CZ00000019.p12` (password: `aaaa1111`)
- `CA_EET-Playground-CZ683555118.p12`
- `CA_EET-Playground-CZ8551015704.p12`

These are automatically published to `storage/eet/certs/` via `vendor:publish --tag=eet-certs`.

### JWT certificate renewal (CAEET API)

The package supports automated certificate renewal via the CAEET REST API. Certificates are renewed as JWT tokens (RS256, `x5t#S256` header) before expiry.

```env
EET_JWT_RENEWAL_ENABLED=true
EET_JWT_API_URL=https://caeet.gov.cz/api
EET_RENEW_DAYS_BEFORE=14
```

## XML Validation

The package bundles the official EET XSD schema and can validate both request and response XML before/after submission:

```php
use Pomocnik\Eet\Xml\XmlValidator;

$validator = app(XmlValidator::class);

$trzbaXml = app(XmlBuilder::class)->buildTrzbaXml($request);
$validator->validateTrzba($trzbaXml); // true or throws XmlValidationException
```

Disable validation in config if needed:

```env
EET_VALIDATE_XML=false
```

## Exceptions

All exceptions extend `Pomocnik\Eet\Exceptions\EetException` (which extends `RuntimeException`).

| Exception | When |
|-----------|------|
| `CertificateException` | Certificate file not found, invalid, or missing private key |
| `SoapException` | SOAP transport failure or unparseable server response |
| `XmlValidationException` | XML fails XSD schema validation |
| `EetException` | XML signing failure or PKP generation failure |

## Low-Level Components

### XmlBuilder

```php
$xmlBuilder = app(XmlBuilder::class);

$trzbaXml = $xmlBuilder->buildTrzbaXml($request);                // bare <v4:Trzba>
$unsignedEnvelope = $xmlBuilder->buildSoapEnvelope($request);     // <soapenv:Envelope>
$signedEnvelope = $xmlBuilder->buildSignedSoapEnvelope($request, $certManager); // WS-Security signed
```

### PkpGenerator & BkpGenerator

```php
$pkp = app(PkpGenerator::class)->generate($request, $certManager); // base64 RSA-SHA256
$bkp = app(BkpGenerator::class)->generate($pkp);                   // UUID-like hex
```

### XmlSigner

Signs a SOAP envelope with WS-Security XML Digital Signature using Exclusive C14N and RSA-SHA256.

## EET 2.0 Timeline

- **1.7.2026** — Playground available for testing
- **1.1.2027** — Pilot mode (mandatory for some industries)
- **1.2.2027** — Full production launch

## Testing

The package includes 91 tests (PHPUnit 11 + Orchestra Testbench):

```bash
cd packages/eet-client
php vendor/bin/phpunit
```

To run the live Playground test (requires internet):

```bash
php vendor/bin/phpunit --filter=EetServiceTest::testSubmitToPlayground
```

## License

MIT
