<?php

namespace App\Models;

use App\Enums\BillableStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disbursement extends Model
{
    protected $fillable = [
        'matter_id', 'date', 'description', 'supplier', 'cost_amount',
        'cost_currency', 'markup_pct', 'amount', 'currency_code', 'status',
        'invoice_line_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cost_amount' => 'decimal:2',
            'markup_pct' => 'decimal:2',
            'amount' => 'decimal:2',
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
