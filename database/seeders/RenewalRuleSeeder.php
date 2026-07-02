<?php

namespace Database\Seeders;

use App\Enums\MatterType;
use App\Models\RenewalRule;
use Illuminate\Database\Seeder;

/**
 * Default renewal schedule templates: type-wide conventions plus the
 * well-known jurisdiction exceptions. Firms can refine these (and add
 * more country overrides) in Renewals → Schedule Rules.
 */
class RenewalRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Patent Annuities (default)',
                'matter_type' => MatterType::Patent,
                'country_code' => null,
                'base_date' => 'application',
                'start_cycle' => 2,
                'end_cycle' => 20,
                'interval_years' => 1,
                'notes' => 'Annuities for years 2–20 counted from the filing date — the most common international convention.',
            ],
            [
                'name' => 'US Patent Maintenance Fees',
                'matter_type' => MatterType::Patent,
                'country_code' => 'US',
                'base_date' => 'registration',
                'offsets_months' => [42, 90, 138], // 3.5 / 7.5 / 11.5 years from grant
                'notes' => 'USPTO maintenance fees fall due 3.5, 7.5 and 11.5 years after grant, with a 6-month surcharge window.',
            ],
            [
                'name' => 'EP Patent Annuities',
                'matter_type' => MatterType::Patent,
                'country_code' => 'EP',
                'base_date' => 'application',
                'start_cycle' => 3,
                'end_cycle' => 20,
                'interval_years' => 1,
                'notes' => 'EPO annuities start with year 3 from filing; after grant they transfer to the national validations.',
            ],
            [
                'name' => 'Trade Mark Renewals (default)',
                'matter_type' => MatterType::Trademark,
                'country_code' => null,
                'base_date' => 'application',
                'start_cycle' => 1,
                'end_cycle' => 5,
                'interval_years' => 10,
                'notes' => 'Ten-year renewal terms counted from the filing date.',
            ],
            [
                'name' => 'US Trade Mark Maintenance',
                'matter_type' => MatterType::Trademark,
                'country_code' => 'US',
                'base_date' => 'registration',
                'offsets_months' => [66, 120, 240, 360, 480], // §8 window + 10-year renewals
                'notes' => '§8 declaration of use due in the 5th–6th year after registration, then combined §8/§9 renewals every 10 years.',
            ],
            [
                'name' => 'Design Renewals (default)',
                'matter_type' => MatterType::Design,
                'country_code' => null,
                'base_date' => 'application',
                'start_cycle' => 1,
                'end_cycle' => 5,
                'interval_years' => 5,
                'notes' => 'Five-year terms up to 25 years, counted from the filing date.',
            ],
            [
                'name' => 'US Design Patent (no maintenance)',
                'matter_type' => MatterType::Design,
                'country_code' => 'US',
                'base_date' => 'registration',
                'offsets_months' => [],
                'notes' => 'US design patents require no maintenance fees — nothing is generated.',
            ],
            [
                'name' => 'Domain Name Renewals',
                'matter_type' => MatterType::Domain,
                'country_code' => null,
                'base_date' => 'application',
                'start_cycle' => 1,
                'end_cycle' => 10,
                'interval_years' => 1,
                'notes' => 'Annual registrations from the registration date recorded as the filing date.',
            ],
        ];

        foreach ($rules as $rule) {
            RenewalRule::updateOrCreate(
                ['matter_type' => $rule['matter_type'], 'country_code' => $rule['country_code'] ?? null],
                $rule + ['grace_months' => 6, 'is_active' => true]
            );
        }
    }
}
