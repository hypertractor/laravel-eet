<?php

namespace Pomocnik\Eet\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Pomocnik\Eet\DTOs\EetResult;

class EetSubmissionSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly EetResult $result,
    ) {}
}
