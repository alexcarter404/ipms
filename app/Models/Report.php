<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/** A saved report definition: a type, its filters, and an optional email schedule. */
class Report extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['name', 'type', 'filters', 'schedule', 'created_by', 'last_run_at'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
