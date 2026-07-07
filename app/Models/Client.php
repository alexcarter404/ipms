<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'email', 'phone', 'country_code', 'notes',
    ];

    /** Users behind this client's ethical wall (empty = no wall). */
    public function walls(): HasMany
    {
        return $this->hasMany(ClientWall::class);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin() || ! $this->walls()->exists()) {
            return true;
        }

        return $this->walls()->where('user_id', $user->id)->exists();
    }

    /** Walled clients only show to their wall members (and admins). */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->whereDoesntHave('walls')
            ->orWhereHas('walls', fn ($w) => $w->where('user_id', $user->id)));
    }

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
