<?php

namespace App\Actions\RenewalRules;

use App\Models\RenewalRule;

class SaveRenewalRule
{
    public function create(array $data): RenewalRule
    {
        return RenewalRule::create($this->normalize($data));
    }

    public function update(RenewalRule $rule, array $data): RenewalRule
    {
        $rule->update($this->normalize($data));

        return $rule;
    }

    /**
     * A rule is either regular cycles or fixed offsets — never both.
     * The unused shape is cleared so the scheduler reads one schedule.
     */
    private function normalize(array $data): array
    {
        if ($data['schedule_mode'] === 'fixed') {
            $data['offsets_months'] = array_values($data['offsets_months'] ?? []);
            $data['start_cycle'] = null;
            $data['end_cycle'] = null;
            $data['interval_years'] = null;
        } else {
            $data['offsets_months'] = null;
        }

        unset($data['schedule_mode']);

        if (! empty($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        return $data;
    }
}
