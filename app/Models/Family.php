<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    use HasFactory;

    protected $table = 'families';

    protected $fillable = ['reference', 'name', 'notes'];

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }
}
