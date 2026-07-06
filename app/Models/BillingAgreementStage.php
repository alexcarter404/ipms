<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillingAgreementStage extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['billing_agreement_id', 'description', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(BillingAgreement::class, 'billing_agreement_id');
    }

    /** The charge raised when this milestone was reached, if any. */
    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class, 'stage_id');
    }
}
