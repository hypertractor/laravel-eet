<?php

namespace Pomocnik\Eet\Models;

use Illuminate\Database\Eloquent\Model;

class EetSubmission extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'receipt_id',
        'customer_invoice_id',
        'uuid_zpravy',
        'fik_code',
        'bkp_code',
        'pkp_code',
        'eet_status',
        'error_code',
        'error_message',
        'endpoint_url',
        'test_mode',
        'request_xml',
        'response_xml',
        'submitted_at',
    ];

    protected $casts = [
        'test_mode' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────

    public function receipt()
    {
        return $this->belongsTo(\App\Models\App\Receipt::class);
    }

    public function customerInvoice()
    {
        return $this->belongsTo(\App\Models\App\CustomerInvoice::class);
    }
}
