<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\BillableStatus;
use App\Enums\ChargeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A non-time, non-disbursement billable: a fixed fee, a stage payment
 * raised against an agreement milestone, or any other one-off charge.
 */
class Charge extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'matter_id', 'stage_id', 'type', 'date', 'description', 'amount', 'base_amount',
        'currency_code', 'status', 'invoice_line_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChargeType::class,
            'date' => 'date',
            'amount' => Money::class,
            'base_amount' => Money::class,
            'status' => BillableStatus::class,
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(BillingAgreementStage::class, 'stage_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('status', BillableStatus::Billable);
    }
}
