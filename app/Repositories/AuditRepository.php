<?php

namespace App\Repositories;

use App\Models\Budget;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Disbursement;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\OfficeSubmission;
use App\Models\Renewal;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Models\Audit;

/**
 * Read-side of the audit log: everything that happened to a record and
 * the records that hang off it, newest first, shaped for the History
 * timeline.
 */
class AuditRepository
{
    /** Human labels for the audited model types. */
    private const LABELS = [
        Matter::class => 'Matter',
        MatterTask::class => 'Task',
        Renewal::class => 'Renewal',
        Communication::class => 'Communication',
        TimeEntry::class => 'Time entry',
        Disbursement::class => 'Disbursement',
        Charge::class => 'Charge',
        Budget::class => 'Budget',
        OfficeSubmission::class => 'Office submission',
        Client::class => 'Client',
        ClientEntity::class => 'Entity',
        Contact::class => 'Contact',
    ];

    /** The matter's own audits plus those of its docket and billing children. */
    public function forMatter(Matter $matter): array
    {
        return $this->timeline([
            Matter::class => [$matter->id],
            MatterTask::class => $matter->tasks()->pluck('id')->all(),
            Renewal::class => $matter->renewals()->pluck('id')->all(),
            Communication::class => $matter->communications()->pluck('id')->all(),
            TimeEntry::class => $matter->timeEntries()->pluck('id')->all(),
            Disbursement::class => $matter->disbursements()->pluck('id')->all(),
            Charge::class => $matter->charges()->pluck('id')->all(),
            Budget::class => $matter->budgets()->pluck('id')->all(),
            OfficeSubmission::class => $matter->submissions()->pluck('id')->all(),
        ]);
    }

    /** The client's own audits plus its entities and contacts. */
    public function forClient(Client $client): array
    {
        return $this->timeline([
            Client::class => [$client->id],
            ClientEntity::class => $client->entities()->pluck('id')->all(),
            Contact::class => $client->contacts()->pluck('id')->all(),
        ]);
    }

    /** @param  array<class-string, list<int>>  $scopes */
    private function timeline(array $scopes): array
    {
        $scopes = array_filter($scopes);

        if ($scopes === []) {
            return [];
        }

        $audits = Audit::query()
            ->where(function (Builder $query) use ($scopes) {
                foreach ($scopes as $type => $ids) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('auditable_type', $type)
                        ->whereIn('auditable_id', $ids));
                }
            })
            ->with(['user:id,name', 'auditable'])
            ->latest()
            ->latest('id')
            ->limit(100)
            ->get();

        return $audits->map(fn (Audit $audit) => [
            'id' => $audit->id,
            'event' => $audit->event,
            'subject_type' => self::LABELS[$audit->auditable_type] ?? class_basename($audit->auditable_type),
            'subject_label' => $this->subjectLabel($audit),
            'user' => $audit->user?->name ?? 'System',
            'at' => $audit->created_at->toDateTimeString(),
            'at_human' => $audit->created_at->diffForHumans(),
            'changes' => $this->changes($audit),
            // Created and update entries capture a restorable state;
            // a delete entry leaves nothing to apply
            'can_transition' => in_array($audit->event, ['created', 'updated'], true)
                && ! empty($audit->new_values),
        ])->all();
    }

    /** Something recognisable to hang the entry on: a title, name or description. */
    private function subjectLabel(Audit $audit): ?string
    {
        // Prefer the live record; fall back to the audit's own snapshot
        // (which is all that's left once the record has been deleted).
        $sources = array_filter([
            $audit->auditable?->getAttributes(),
            ($audit->new_values ?: []) + ($audit->old_values ?: []),
        ]);

        foreach ($sources as $values) {
            foreach (['reference', 'title', 'name', 'description', 'narrative', 'subject'] as $key) {
                if (! empty($values[$key]) && is_string($values[$key])) {
                    return \Illuminate\Support\Str::limit($values[$key], 60);
                }
            }
        }

        return null;
    }

    /**
     * Money columns audit as raw integer minor units; show them in the
     * major units the reader expects.
     *
     * @var array<class-string, list<string>>
     */
    private const MONEY_FIELDS = [
        TimeEntry::class => ['rate', 'amount', 'base_amount'],
        Disbursement::class => ['cost_amount', 'amount', 'base_amount'],
        Charge::class => ['amount', 'base_amount'],
        Budget::class => ['amount', 'base_amount'],
        Renewal::class => ['official_fee', 'service_fee'],
    ];

    /** @return list<array{field: string, old: mixed, new: mixed}> */
    private function changes(Audit $audit): array
    {
        $moneyFields = self::MONEY_FIELDS[$audit->auditable_type] ?? [];

        return collect($audit->getModified())
            // Bookkeeping columns say nothing a reader needs
            ->reject(fn ($change, $field) => $field === 'id'
                || str_ends_with($field, '_id')
                || str_ends_with($field, '_by'))
            ->map(fn ($change, $field) => [
                'field' => str_replace('_', ' ', $field),
                'old' => $this->displayValue($change['old'] ?? null, in_array($field, $moneyFields, true)),
                'new' => $this->displayValue($change['new'] ?? null, in_array($field, $moneyFields, true)),
            ])
            ->values()
            ->all();
    }

    private function displayValue(mixed $value, bool $isMoney = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($isMoney && is_numeric($value)) {
            return number_format(\App\Support\MoneyMinor::toMajor($value), 2, '.', '');
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return \Illuminate\Support\Str::limit(json_encode($value), 80);
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        // Audit values arrive as raw strings; tame ISO timestamps
        if (is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $value, $m)) {
            return "{$m[1]} {$m[2]}";
        }

        return \Illuminate\Support\Str::limit((string) $value, 120);
    }
}
