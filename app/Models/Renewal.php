<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use App\Enums\RenewalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renewal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $fillable = [
        'matter_id', 'cycle', 'due_date', 'grace_date', 'status',
        'official_fee', 'service_fee', 'currency', 'instructed_at',
        'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RenewalStatus::class,
            'due_date' => 'date',
            'grace_date' => 'date',
            'official_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'instructed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['upcoming', 'reminder_sent', 'instructed']);
    }

    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->whereDate('due_date', '<=', now()->addDays($days));
    }
}
