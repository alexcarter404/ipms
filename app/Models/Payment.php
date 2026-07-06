<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['invoice_id', 'date', 'amount', 'method', 'reference'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
