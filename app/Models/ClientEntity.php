<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'name', 'registration_no', 'vat_number', 'country_code',
        'address', 'billing_contact_name', 'billing_email', 'billing_address',
        'billing_reference', 'currency_code', 'tax_rate_id', 'is_default', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /** The tax treatment applied to this entity's invoices. */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** Make this the client's default entity (exactly one per client). */
    public function makeDefault(): void
    {
        $this->client->entities()->whereKeyNot($this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    /** Where invoices for this entity should go. */
    public function effectiveBillingAddress(): ?string
    {
        return $this->billing_address ?? $this->address;
    }
}
