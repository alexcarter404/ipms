<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One comparison of a matter against the office register: clean,
 * drifted (with the field-level differences), or unknown to the
 * office.
 */
class RegisterCheck extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'matter_id', 'office', 'status', 'differences', 'checked_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'differences' => 'array',
            'checked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }
}
