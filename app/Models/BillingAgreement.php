<?php

namespace App\Models;

use App\Enums\AgreementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAgreement extends Model
{
    protected $fillable = [
        'matter_id', 'type', 'currency_code', 'increment_minutes',
        'blended_rate', 'cap_amount', 'fixed_amount', 'default_markup_pct',
        'requires_task_codes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => AgreementType::class,
            'blended_rate' => 'decimal:2',
            'cap_amount' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'default_markup_pct' => 'decimal:2',
            'requires_task_codes' => 'boolean',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(BillingAgreementStage::class)->orderBy('sort_order');
    }
}
