<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'invoice_no', 'client_id', 'client_entity_id', 'matter_id',
        'currency_code', 'status', 'issued_at', 'due_at', 'tax_name',
        'tax_pct', 'subtotal', 'tax_amount', 'total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'tax_pct' => 'decimal:2',
            'subtotal' => \App\Casts\Money::class,
            'tax_amount' => \App\Casts\Money::class,
            'total' => \App\Casts\Money::class,
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
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('date');
    }

    public function amountPaid(): float
    {
        return \App\Support\MoneyMinor::fromMinor($this->payments()->sum('amount'));
    }

    public function balance(): float
    {
        return round((float) $this->total - $this->amountPaid(), 2);
    }

    /** The label shown before an invoice number is assigned at issue. */
    public function displayNumber(): string
    {
        return $this->invoice_no ?? "Draft #{$this->id}";
    }
}
