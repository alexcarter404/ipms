<?php

namespace App\Models;

use App\Enums\BillableStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $fillable = [
        'matter_id', 'user_id', 'activity_code_id', 'work_date', 'minutes',
        'billed_minutes', 'rate', 'currency_code', 'amount', 'base_amount', 'narrative',
        'status', 'invoice_line_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'status' => BillableStatus::class,
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityCode(): BelongsTo
    {
        return $this->belongsTo(ActivityCode::class);
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
