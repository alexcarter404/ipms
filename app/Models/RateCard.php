<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An hourly rate scoped by timekeeper and/or client. Null user = any
 * timekeeper; null client = firm-wide. The most specific card wins.
 */
class RateCard extends Model
{
    protected $fillable = ['user_id', 'client_id', 'currency_code', 'hourly_rate', 'effective_from'];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
