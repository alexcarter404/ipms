<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

class ActivityCode extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['code', 'description'];
}
