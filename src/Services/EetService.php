<?php

namespace Pomocnik\Eet\Services;

use Illuminate\Support\Facades\Log;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Crypto\BkpGenerator;
use Pomocnik\Eet\Crypto\PkpGenerator;
use Pomocnik\Eet\DTOs\EetRequest;
use Pomocnik\Eet\DTOs\EetResult;
use Pomocnik\Eet\Events\EetSubmissionFailed;
use Pomocnik\Eet\Events\EetSubmissionSucceeded;
use Pomocnik\Eet\Exceptions\EetException;
use Pomocnik\Eet\Soap\EetSoapClient;

class EetService
{
    public function __construct(
        protected CertificateManager $certificates,
        protected EetSoapClient $soapClient,
        protected PkpGenerator $pkpGenerator,
        protected BkpGenerator $bkpGenerator,
    ) {}

    /**
     * Odesle trzbu do EET.
     */
    public function submit(EetRequest $request): EetResult
    {
        // Vygenerovat PKP a BKP
        $pkp = $this->pkpGenerator->generate($request, $this->certificates);
        $bkp = $this->bkpGenerator->generate($pkp);

        // Odeslat na EET endpoint
        $endpoint = $this->getEndpoint();

        try {
            $response = $this->soapClient->sendRequest($request, $endpoint);
        } catch (EetException $e) {
            Log::error('EET submission failed', [
                'uuid' => $request->uuidZpravy,
                'error' => $e->getMessage(),
            ]);

            return EetResult::failure(
                errorCode: -1,
                errorMessage: $e->getMessage(),
                uuidZpravy: $request->uuidZpravy,
            );
        }

        // Zpracovat odpoved
        if ($response->success) {
            $result = EetResult::success(
                fikCode: $response->fikCode,
                testFikCode: $response->testFikCode,
                bkpCode: $bkp,
                pkpCode: $pkp,
                uuidZpravy: $request->uuidZpravy,
            );

            event(new EetSubmissionSucceeded($result));

            return $result;
        }

        $result = EetResult::failure(
            errorCode: $response->errorCode,
            errorMessage: $response->errorMessage,
            uuidZpravy: $request->uuidZpravy,
            rawResponse: $response->rawXml,
        );

        event(new EetSubmissionFailed($result));

        return $result;
    }

    /**
     * Vrati endpoint URL podle aktualniho modu.
     */
    public function getEndpoint(): string
    {
        return config('eet.test_mode')
            ? config('eet.endpoint_playground')
            : config('eet.endpoint_production');
    }

    /**
     * Vytvori EetRequest z receipt dat.
     */
    public function createRequestFromReceipt(array $receiptData): EetRequest
    {
        return EetRequest::fromReceipt(
            data: $receiptData,
            unitId: config('eet.unit_id', '1'),
            terminalId: config('eet.terminal_id', '1'),
        );
    }
}
