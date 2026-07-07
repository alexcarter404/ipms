<?php

namespace App\Actions\Renewals;

use App\Exceptions\DomainActionException;
use App\Models\Matter;
use App\Models\Renewal;
use App\Services\RenewalScheduler;
use Illuminate\Support\Collection;

class GenerateRenewalSchedule
{
    public function __construct(private RenewalScheduler $scheduler) {}

    /**
     * Generate the matter's schedule from its governing rule, explaining
     * precisely why nothing could be generated otherwise.
     *
     * @return Collection<int, Renewal> the newly created renewals
     */
    public function handle(Matter $matter): Collection
    {
        $rule = $this->scheduler->ruleFor($matter);

        if (! $rule) {
            throw new DomainActionException(
                "No renewal rule is configured for {$matter->matter_type->label()} matters in {$matter->country_code} — add one under Renewals → Schedule Rules."
            );
        }

        if (! $rule->baseDateFor($matter)) {
            throw new DomainActionException(
                "The rule “{$rule->name}” anchors on the {$rule->baseDateLabel()}, which this matter does not have yet."
            );
        }

        $created = $this->scheduler->generate($matter);

        if ($created->isEmpty()) {
            throw new DomainActionException(
                "No renewals generated — the “{$rule->name}” schedule produced no upcoming cycles (they may already exist, be long past, or the rule defines no renewals for this right)."
            );
        }

        return $created;
    }

    public function ruleName(Matter $matter): ?string
    {
        return $this->scheduler->ruleFor($matter)?->name;
    }
}
