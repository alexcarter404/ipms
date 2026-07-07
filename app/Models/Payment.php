<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Payment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['invoice_id', 'date', 'amount', 'method', 'reference'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => Money::class,
            'method' => PaymentMethod::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
