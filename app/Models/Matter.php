<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use App\Enums\MatterStatus;
use App\Enums\MatterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matter extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'matter_type', 'title', 'client_id', 'client_entity_id',
        'family_id', 'parent_id', 'responsible_user_id', 'country_code',
        'filing_route', 'status', 'application_no', 'application_date',
        'publication_no', 'publication_date', 'registration_no',
        'registration_date', 'priority_no', 'priority_date', 'expiry_date',
        'description', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'matter_type' => MatterType::class,
            'status' => MatterStatus::class,
            'application_date' => 'date',
            'publication_date' => 'date',
            'registration_date' => 'date',
            'priority_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** A matter is only as visible as its client's ethical wall allows. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('client', fn ($q) => $q->visibleTo($user));
    }

    public function billingEntity(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_entity_id');
    }

    /** The entity billed for this matter — explicit, or the client's default. */
    public function effectiveBillingEntity(): ?ClientEntity
    {
        return $this->billingEntity ?? $this->client?->defaultEntity();
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'matter_contact')
            ->withPivot('role')
            ->withTimestamps()
            ->orderByPivot('role');
    }

    /** The main correspondence contact, falling back to the client's primary contact. */
    public function mainContact(): ?Contact
    {
        return $this->contacts->firstWhere('pivot.role', 'main')
            ?? $this->client?->contacts()->orderByDesc('is_primary')->first();
    }

    /** Where docketing correspondence for this matter goes, if configured. */
    public function docketingContact(): ?Contact
    {
        return $this->contacts->firstWhere('pivot.role', 'docketing');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'matter_party')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(MatterClass::class)->orderBy('class_number');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class)->orderBy('due_date');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MatterTask::class)->orderBy('due_date');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class)->latest();
    }

    public function billingAgreement(): HasOne
    {
        return $this->hasOne(BillingAgreement::class);
    }

    /**
     * The fee agreement governing this matter: its own override, or the
     * default handed down from its billing entity.
     */
    public function effectiveBillingAgreement(): ?BillingAgreement
    {
        return $this->billingAgreement
            ?? $this->effectiveBillingEntity()?->billingAgreement;
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderByDesc('work_date');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class)->orderByDesc('date');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class)->orderByDesc('date');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class)->latest();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(OfficeSubmission::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest();
    }

    /** The currency this matter is billed in: agreement > entity > firm base. */
    public function billingCurrency(): string
    {
        return $this->effectiveBillingAgreement()?->currency_code
            ?? $this->effectiveBillingEntity()?->currency_code
            ?? config('billing.base_currency');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['abandoned', 'lapsed', 'expired', 'closed']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('application_no', 'like', "%{$term}%")
                ->orWhere('registration_no', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"));
        });
    }
}
