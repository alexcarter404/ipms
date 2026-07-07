<?php

namespace App\Services;

use App\Enums\RenewalStatus;
use App\Models\Matter;
use App\Models\Renewal;
use App\Models\RenewalRule;
use App\Repositories\RenewalRuleRepository;
use Illuminate\Support\Collection;

/**
 * Generates a renewal/annuity schedule for a matter from the renewal
 * rule (schedule template) that matches its type and jurisdiction.
 *
 * Rules are data-driven (see RenewalRule / RenewalRuleSeeder): a
 * country-specific rule wins over the type-wide default, so e.g. US
 * patents get maintenance fees at 3.5/7.5/11.5 years from grant while
 * other patents get annuities for years 2–20 from filing.
 */
class RenewalScheduler
{
    public function __construct(private RenewalRuleRepository $rules) {}

    /** The rule that would govern this matter's schedule, if any. */
    public function ruleFor(Matter $matter): ?RenewalRule
    {
        return $this->rules->resolveFor($matter->matter_type, $matter->country_code);
    }

    /**
     * Create any missing renewals for the matter. Existing cycles are
     * left untouched, so the method is safe to re-run.
     *
     * @return Collection<int, Renewal> the newly created renewals
     */
    public function generate(Matter $matter): Collection
    {
        $rule = $this->ruleFor($matter);
        $base = $rule?->baseDateFor($matter);

        if (! $rule || ! $base) {
            return collect();
        }

        $existing = $matter->renewals()->pluck('cycle')->all();
        $created = collect();

        foreach ($rule->schedule($base) as $cycle => $dueDate) {
            if (in_array($cycle, $existing)) {
                continue;
            }

            // Don't create renewals that were already due more than a
            // year ago; historic data should be imported, not generated.
            if ($dueDate->lt(now()->subYear())) {
                continue;
            }

            if ($matter->expiry_date && $dueDate->gt($matter->expiry_date)) {
                continue;
            }

            $created->push($matter->renewals()->create([
                'cycle' => $cycle,
                'due_date' => $dueDate,
                'grace_date' => $dueDate->copy()->addMonths($rule->grace_months),
                'status' => RenewalStatus::Upcoming,
                'official_fee' => $rule->default_official_fee,
                'service_fee' => $rule->default_service_fee,
                'currency' => $rule->currency ?? 'USD',
            ]));
        }

        return $created;
    }
}
