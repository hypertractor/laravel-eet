<?php

$certsDir = __DIR__.'/../certs';

return [

    /*
    |--------------------------------------------------------------------------
    | EET Endpoint URLs
    |--------------------------------------------------------------------------
    */

    'endpoint_playground' => env('EET_ENDPOINT_PLAYGROUND', 'https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4'),
    'endpoint_production' => env('EET_ENDPOINT_PRODUCTION', 'https://trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4'),

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    | When true, the playground endpoint is used.
    */

    'test_mode' => (bool) env('EET_TEST_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Default Unit and Terminal IDs
    |--------------------------------------------------------------------------
    */

    'unit_id' => env('EET_UNIT_ID', '1'),
    'terminal_id' => env('EET_TERMINAL_ID', '1'),

    /*
    |--------------------------------------------------------------------------
    | Certificate Settings (.p12)
    |--------------------------------------------------------------------------
    */

    'certificate' => [
        'path' => env('EET_CERTIFICATE_PATH', $certsDir.'/CA_EET-Playground-CZ00000019.p12'),
        'password' => env('EET_CERTIFICATE_PASSWORD', 'aaaa1111'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CA Certificate Chain (for TLS verification)
    |--------------------------------------------------------------------------
    */

    'ca_certificates' => [
        'playground_root' => env('EET_CA_ROOT_PLAYGROUND', null),
        'playground_sub' => env('EET_CA_SUB_PLAYGROUND', null),
        'production_root' => env('EET_CA_ROOT_PRODUCTION', null),
        'production_sub' => env('EET_CA_SUB_PRODUCTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Certificate Renewal (CAEET API)
    |--------------------------------------------------------------------------
    */

    'jwt_renewal' => [
        'enabled' => (bool) env('EET_JWT_RENEWAL_ENABLED', false),
        'api_url' => env('EET_JWT_API_URL', 'https://caeet.gov.cz/api'),
        'renew_days_before_expiry' => (int) env('EET_RENEW_DAYS_BEFORE', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeouts
    |--------------------------------------------------------------------------
    */

    'timeouts' => [
        'soap' => (float) env('EET_SOAP_TIMEOUT', 30.0),
        'jwt' => (float) env('EET_JWT_TIMEOUT', 10.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */

    'retries' => [
        'max_attempts' => (int) env('EET_RETRY_MAX_ATTEMPTS', 3),
        'delay_ms' => (int) env('EET_RETRY_DELAY_MS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | XML Validation
    |--------------------------------------------------------------------------
    */

    'validate_xml' => (bool) env('EET_VALIDATE_XML', true),

];
