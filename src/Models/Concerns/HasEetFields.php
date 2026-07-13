<?php

namespace Pomocnik\Eet\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait pridavajici EET pole do Receipt modelu.
 *
 * @property string|null $fik_code
 * @property string|null $bkp_code
 * @property string|null $pkp_code
 * @property string $eet_status
 * @property \Illuminate\Support\Carbon|null $eet_submitted_at
 * @property bool $eet_first_send
 * @property bool $eet_test_mode
 * @property string|null $eet_uuid
 */
trait HasEetFields
{
    public static function bootHasEetFields(): void
    {
        // Zakladni hodnoty pri vytvoreni
        static::creating(function (Model $model) {
            if ($model->eet_status === null) {
                $model->eet_status = 'pending';
            }

            if ($model->eet_first_send === null) {
                $model->eet_first_send = true;
            }

            if ($model->eet_test_mode === null) {
                $model->eet_test_mode = (bool) config('eet.test_mode', true);
            }
        });
    }

    // ─── Accessory ──────────────────────────────────────────────────

    protected function isEetRegistered(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->eet_status === 'sent',
        );
    }

    protected function isEetPending(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->eet_status === 'pending',
        );
    }

    protected function isEetFailed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->eet_status === 'failed',
        );
    }

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeEetPending(Builder $query): Builder
    {
        return $query->where('eet_status', 'pending');
    }

    public function scopeEetSent(Builder $query): Builder
    {
        return $query->where('eet_status', 'sent');
    }

    public function scopeEetFailed(Builder $query): Builder
    {
        return $query->where('eet_status', 'failed');
    }

    public function scopeEetMandatory(Builder $query): Builder
    {
        return $query->whereHas('paymentType', function ($q) {
            $q->where('eet_mandatory', true);
        });
    }
}
