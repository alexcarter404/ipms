<?php

namespace App\Services;

use App\Enums\MatterType;
use App\Enums\RenewalStatus;
use App\Models\Matter;
use App\Models\Renewal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Generates a renewal/annuity schedule for a matter based on its type.
 *
 * Rules follow the most common international conventions and can be
 * refined per jurisdiction later:
 *  - Patents:    annuities for years 2-20 counted from the filing date
 *  - Trade marks: renewal every 10 years from the filing date
 *  - Designs:    renewal every 5 years from the filing date, max 25 years
 *  - Domains:    yearly renewal from the registration/filing date
 */
class RenewalScheduler
{
    /**
     * Create any missing renewals for the matter. Existing cycles are
     * left untouched, so the method is safe to re-run.
     *
     * @return Collection<int, Renewal> the newly created renewals
     */
    public function generate(Matter $matter): Collection
    {
        $base = $matter->application_date ?? $matter->registration_date;

        if (! $base) {
            return collect();
        }

        $existing = $matter->renewals()->pluck('cycle')->all();
        $created = collect();

        foreach ($this->schedule($matter, $base) as $cycle => $dueDate) {
            if (in_array($cycle, $existing)) {
                continue;
            }

            // Don't create renewals that were already due more than a
            // year ago; historic data should be imported, not generated.
            if ($dueDate->lt(now()->subYear())) {
                continue;
            }

            $created->push($matter->renewals()->create([
                'cycle' => $cycle,
                'due_date' => $dueDate,
                'grace_date' => $dueDate->copy()->addMonths(6),
                'status' => RenewalStatus::Upcoming,
            ]));
        }

        return $created;
    }

    /**
     * @return array<int, CarbonInterface> cycle number => due date
     */
    private function schedule(Matter $matter, CarbonInterface $base): array
    {
        $dates = [];

        switch ($matter->matter_type) {
            case MatterType::Patent:
                foreach (range(2, 20) as $year) {
                    $dates[$year] = $base->copy()->addYears($year);
                }
                break;

            case MatterType::Trademark:
                foreach (range(1, 5) as $term) {
                    $dates[$term] = $base->copy()->addYears($term * 10);
                }
                break;

            case MatterType::Design:
                foreach (range(1, 5) as $term) {
                    $dates[$term] = $base->copy()->addYears($term * 5);
                }
                break;

            case MatterType::Domain:
                foreach (range(1, 10) as $year) {
                    $dates[$year] = $base->copy()->addYears($year);
                }
                break;

            default:
                break;
        }

        if ($matter->expiry_date) {
            $dates = array_filter($dates, fn ($d) => $d->lte($matter->expiry_date));
        }

        return $dates;
    }
}
