<?php

namespace Tests\Feature;

use App\Models\ActivityCode;
use App\Models\Client;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'attorney']);
        $this->matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'matter_type' => 'patent',
        ]);
    }

    private function loggedRate(array $extra = []): string
    {
        $this->actingAs($this->user)->post(route('matters.time.store', $this->matter), array_merge([
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ], $extra));

        return $this->matter->timeEntries()->latest('id')->first()->rate;
    }

    public function test_a_grade_rule_values_time_when_no_personal_rule_exists(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        RateCard::create(['role' => 'attorney', 'currency_code' => 'GBP', 'hourly_rate' => 240, 'effective_from' => '2020-01-01']);

        $this->assertSame('240.00', $this->loggedRate());
    }

    public function test_a_personal_rule_beats_any_grade_rule(): void
    {
        RateCard::create(['role' => 'attorney', 'client_id' => $this->matter->client_id, 'matter_type' => 'patent', 'currency_code' => 'GBP', 'hourly_rate' => 500, 'effective_from' => '2020-01-01']);
        RateCard::create(['user_id' => $this->user->id, 'currency_code' => 'GBP', 'hourly_rate' => 260, 'effective_from' => '2020-01-01']);

        // user (16) outranks role+client+type (8+4+2=14)
        $this->assertSame('260.00', $this->loggedRate());
    }

    public function test_a_client_scoped_grade_rule_beats_a_plain_grade_rule(): void
    {
        RateCard::create(['role' => 'attorney', 'currency_code' => 'GBP', 'hourly_rate' => 240, 'effective_from' => '2020-01-01']);
        RateCard::create(['role' => 'attorney', 'client_id' => $this->matter->client_id, 'currency_code' => 'GBP', 'hourly_rate' => 210, 'effective_from' => '2020-01-01']);

        $this->assertSame('210.00', $this->loggedRate());
    }

    public function test_matter_type_rules_differentiate_practice_areas(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        RateCard::create(['matter_type' => 'trademark', 'currency_code' => 'GBP', 'hourly_rate' => 180, 'effective_from' => '2020-01-01']);

        // A patent matter falls through to the default…
        $this->assertSame('200.00', $this->loggedRate());

        // …a trade mark matter picks up the trademark rate
        $this->matter = Matter::factory()->trademark()->create(['client_id' => $this->matter->client_id]);
        $this->assertSame('180.00', $this->loggedRate());
    }

    public function test_activity_code_rules_price_specific_work(): void
    {
        $oralProceedings = ActivityCode::create(['code' => 'P450', 'description' => 'Oral proceedings']);
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        RateCard::create(['activity_code_id' => $oralProceedings->id, 'currency_code' => 'GBP', 'hourly_rate' => 380, 'effective_from' => '2020-01-01']);

        $this->assertSame('200.00', $this->loggedRate());
        $this->assertSame('380.00', $this->loggedRate(['activity_code_id' => $oralProceedings->id]));
    }

    public function test_ungraded_users_never_match_grade_rules(): void
    {
        $this->user->update(['role' => null]);
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        RateCard::create(['role' => 'attorney', 'currency_code' => 'GBP', 'hourly_rate' => 240, 'effective_from' => '2020-01-01']);

        $this->assertSame('200.00', $this->loggedRate());
    }

    public function test_equal_specificity_resolves_to_the_latest_effective_date(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2024-01-01']);
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 215, 'effective_from' => '2026-01-01']);
        // A future uplift is ignored until it takes effect
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 230, 'effective_from' => '2027-01-01']);

        $this->assertSame('215.00', $this->loggedRate());
    }

    public function test_timekeeper_grades_are_managed_from_billing_settings(): void
    {
        $this->actingAs($this->user)
            ->patch(route('billing.timekeepers.role', $this->user), ['role' => 'partner'])
            ->assertSessionHas('success');

        $this->assertSame('partner', $this->user->fresh()->role->value);

        $this->actingAs($this->user)
            ->get(route('billing.settings'))
            ->assertInertia(fn ($page) => $page
                ->has('roles')
                ->has('matterTypes')
                ->has('timekeepers'));
    }
}
