<?php

namespace Pomocnik\Eet\Facades;

use Illuminate\Support\Facades\Facade;
use Pomocnik\Eet\Services\EetService;

/**
 * @method static \Pomocnik\Eet\DTOs\EetResult submit(\Pomocnik\Eet\DTOs\EetRequest $request)
 * @method static string getEndpoint()
 * @method static \Pomocnik\Eet\DTOs\EetRequest createRequestFromReceipt(array $receiptData)
 *
 * @see \Pomocnik\Eet\Services\EetService
 */
class Eet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EetService::class;
    }
}
