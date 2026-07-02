<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatterClass extends Model
{
    use HasFactory;

    protected $table = 'matter_classes';

    protected $fillable = ['matter_id', 'class_number', 'specification'];

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }
}
