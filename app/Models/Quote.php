<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Quote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'quote_no', 'client_id', 'client_entity_id', 'matter_id',
        'currency_code', 'status', 'valid_until', 'tax_name', 'tax_pct',
        'subtotal', 'tax_amount', 'total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'tax_pct' => 'decimal:2',
            'subtotal' => Money::class,
            'tax_amount' => Money::class,
            'total' => Money::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_entity_id');
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }
}
