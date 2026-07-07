<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One increment of a matter's budget. Budgets accumulate: the matter's
 * total budget is the sum of its rows, each carrying who added it,
 * when, and in what currency (with the base value frozen at entry).
 */
class Budget extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'matter_id', 'created_by', 'description', 'amount', 'currency_code', 'base_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => Money::class,
            'base_amount' => Money::class,
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
