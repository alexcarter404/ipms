<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One increment of a matter's budget. Budgets accumulate: the matter's
 * total budget is the sum of its rows, each carrying who added it,
 * when, and in what currency (with the base value frozen at entry).
 */
class Budget extends Model
{
    protected $fillable = [
        'matter_id', 'created_by', 'description', 'amount', 'currency_code', 'base_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
