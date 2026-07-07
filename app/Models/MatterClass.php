<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MatterClass extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'matter_classes';

    protected $fillable = ['matter_id', 'class_number', 'specification'];

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }
}
