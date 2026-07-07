<?php

namespace App\Models;

use App\Enums\MatterType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class CommTemplate extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'channel', 'matter_type', 'subject', 'body', 'is_active', 'auto_event',
    ];

    protected function casts(): array
    {
        return [
            'matter_type' => MatterType::class,
            'is_active' => 'boolean',
        ];
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }
}
