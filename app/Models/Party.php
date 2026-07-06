<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Party extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'email', 'phone', 'address', 'country_code', 'notes',
    ];

    public function matters(): BelongsToMany
    {
        return $this->belongsToMany(Matter::class, 'matter_party')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }
}
