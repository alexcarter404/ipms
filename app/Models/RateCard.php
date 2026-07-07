<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\MatterType;
use App\Enums\TimekeeperRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A rate rule: an hourly rate scoped by any combination of timekeeper,
 * grade (role), client, matter type and activity code. Null dimensions
 * match anything; the most specific matching rule wins.
 */
class RateCard extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'user_id', 'role', 'client_id', 'matter_type', 'activity_code_id',
        'currency_code', 'hourly_rate', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'role' => TimekeeperRole::class,
            'matter_type' => MatterType::class,
            'hourly_rate' => Money::class,
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

    public function activityCode(): BelongsTo
    {
        return $this->belongsTo(ActivityCode::class);
    }

    /**
     * How specific this rule is. Weights make each dimension outrank
     * every combination of the weaker ones: a personal rate always
     * beats a grade rate, which beats client/matter/activity scoping.
     */
    public function specificity(): int
    {
        return ($this->user_id ? 16 : 0)
            + ($this->role ? 8 : 0)
            + ($this->client_id ? 4 : 0)
            + ($this->matter_type ? 2 : 0)
            + ($this->activity_code_id ? 1 : 0);
    }
}
