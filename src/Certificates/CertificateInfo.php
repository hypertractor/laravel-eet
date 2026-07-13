<?php

namespace Pomocnik\Eet\Certificates;

use Illuminate\Support\Carbon;

class CertificateInfo
{
    public function __construct(
        public readonly string $subject,
        public readonly string $issuer,
        public readonly Carbon $validFrom,
        public readonly Carbon $validTo,
        public readonly string $serialNumber,
        public readonly string $fingerprint,
    ) {}

    public function isExpired(): bool
    {
        return $this->validTo->isPast();
    }

    public function daysUntilExpiry(): int
    {
        return (int) now()->diffInDays($this->validTo, false);
    }

    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'issuer' => $this->issuer,
            'valid_from' => $this->validFrom->toIso8601String(),
            'valid_to' => $this->validTo->toIso8601String(),
            'serial_number' => $this->serialNumber,
            'fingerprint' => $this->fingerprint,
        ];
    }
}
