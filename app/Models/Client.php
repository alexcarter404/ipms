<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'email', 'phone', 'country_code', 'notes',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(ClientEntity::class)
            ->orderByDesc('is_default')
            ->orderBy('name');
    }

    public function defaultEntity(): ?ClientEntity
    {
        return $this->entities()->where('is_default', true)->first()
            ?? $this->entities()->first();
    }

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }
}
