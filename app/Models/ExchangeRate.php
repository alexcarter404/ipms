<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A day's rate against the base currency: 1 base unit = rate × currency.
 */
class ExchangeRate extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['currency_code', 'rate', 'rate_date'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            // rate_date stays a plain Y-m-d string so updateOrCreate's
            // where clause matches the stored value exactly.
        ];
    }
}
