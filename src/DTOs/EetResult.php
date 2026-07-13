<?php

namespace Pomocnik\Eet\DTOs;

class EetResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $fikCode = null,
        public readonly ?string $bkpCode = null,
        public readonly ?string $pkpCode = null,
        public readonly ?string $testFikCode = null,
        public readonly ?int $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $warningCode = null,
        public readonly ?string $rawResponse = null,
        public readonly ?string $uuidZpravy = null,
    ) {}

    public static function success(string $fikCode, ?string $testFikCode, ?string $bkpCode, ?string $pkpCode, string $uuidZpravy): self
    {
        return new self(
            success: true,
            fikCode: $fikCode,
            testFikCode: $testFikCode,
            bkpCode: $bkpCode,
            pkpCode: $pkpCode,
            uuidZpravy: $uuidZpravy,
        );
    }

    public static function failure(int $errorCode, string $errorMessage, ?string $uuidZpravy, ?string $rawResponse = null): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            uuidZpravy: $uuidZpravy,
            rawResponse: $rawResponse,
        );
    }
}
