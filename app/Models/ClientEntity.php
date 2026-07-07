<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class ClientEntity extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

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

    /** The default fee agreement for matters billed to this entity. */
    public function billingAgreement(): HasOne
    {
        return $this->hasOne(BillingAgreement::class);
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

    /**
     * Matters billed to this entity: explicitly assigned ones, plus the
     * client's unassigned matters when this is the default entity.
     */
    public function billedMattersQuery(): Builder
    {
        return Matter::where('client_id', $this->client_id)
            ->where(function (Builder $q) {
                $q->where('client_entity_id', $this->id);

                if ($this->is_default) {
                    $q->orWhereNull('client_entity_id');
                }
            });
    }
}
