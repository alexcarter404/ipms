<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\BillableStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Disbursement extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'matter_id', 'date', 'description', 'supplier', 'cost_amount',
        'cost_currency', 'markup_pct', 'amount', 'base_amount', 'currency_code', 'status',
        'invoice_line_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cost_amount' => Money::class,
            'markup_pct' => 'decimal:2',
            'amount' => Money::class,
            'base_amount' => Money::class,
            'status' => BillableStatus::class,
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
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
