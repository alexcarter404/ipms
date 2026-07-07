<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class InvoiceLine extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'invoice_id', 'matter_id', 'billable_type', 'billable_id',
        'description', 'quantity', 'unit_amount', 'line_total', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_amount' => Money::class,
            'line_total' => Money::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** The WIP item this line bills (a TimeEntry, Disbursement, or Charge). */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
