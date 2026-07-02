<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\RenewalRule;
use App\Models\User;
use App\Services\RenewalScheduler;
use Database\Seeders\RenewalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RenewalRuleSeeder::class);
        $this->user = User::factory()->create();
    }

    public function test_country_specific_rule_wins_over_type_default(): void
    {
        $scheduler = app(RenewalScheduler::class);

        $us = Matter::factory()->create(['country_code' => 'US']);
        $gb = Matter::factory()->create(['country_code' => 'GB']);

        $this->assertSame('US Patent Maintenance Fees', $scheduler->ruleFor($us)->name);
        $this->assertSame('Patent Annuities (default)', $scheduler->ruleFor($gb)->name);
    }

    public function test_us_patent_gets_maintenance_fees_from_grant(): void
    {
        $matter = Matter::factory()->granted()->create([
            'country_code' => 'US',
            'application_date' => '2026-01-15',
            'registration_date' => '2026-06-01',
        ]);

        $created = app(RenewalScheduler::class)->generate($matter);

        // 3.5 / 7.5 / 11.5 years from grant, not annuities from filing
        $this->assertCount(3, $created);
        $this->assertSame(
            ['2029-12-01', '2033-12-01', '2037-12-01'],
            $matter->renewals()->orderBy('cycle')->pluck('due_date')->map->toDateString()->all()
        );
    }

    public function test_us_patent_without_grant_generates_nothing_with_explanation(): void
    {
        $matter = Matter::factory()->create([
            'country_code' => 'US',
            'application_date' => '2026-01-15',
            'registration_date' => null,
        ]);

        $this->actingAs($this->user)
            ->from(route('matters.show', $matter))
            ->post(route('matters.renewals.generate', $matter))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'registration / grant date'));

        $this->assertSame(0, $matter->renewals()->count());
    }

    public function test_ep_annuities_start_at_year_three(): void
    {
        $matter = Matter::factory()->create([
            'country_code' => 'EP',
            'application_date' => now()->subMonths(6),
        ]);

        app(RenewalScheduler::class)->generate($matter);

        $this->assertSame(3, $matter->renewals()->min('cycle'));
        $this->assertSame(18, $matter->renewals()->count());
    }

    public function test_empty_offsets_rule_means_no_renewals(): void
    {
        // US design patents have no maintenance fees
        $matter = Matter::factory()->design()->create([
            'country_code' => 'US',
            'application_date' => now()->subYear(),
            'registration_date' => now()->subMonths(6),
        ]);

        $created = app(RenewalScheduler::class)->generate($matter);

        $this->assertCount(0, $created);
    }

    public function test_inactive_rules_are_ignored(): void
    {
        RenewalRule::where('country_code', 'US')->update(['is_active' => false]);

        $matter = Matter::factory()->create(['country_code' => 'US']);

        // Falls back to the type-wide default
        $this->assertSame(
            'Patent Annuities (default)',
            app(RenewalScheduler::class)->ruleFor($matter)->name
        );
    }

    public function test_no_matching_rule_yields_helpful_error(): void
    {
        RenewalRule::query()->delete();

        $matter = Matter::factory()->create([
            'country_code' => 'GB',
            'application_date' => now()->subYear(),
        ]);

        $this->actingAs($this->user)
            ->from(route('matters.show', $matter))
            ->post(route('matters.renewals.generate', $matter))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'No renewal rule'));
    }

    public function test_rule_default_fees_flow_into_generated_renewals(): void
    {
        RenewalRule::where('name', 'Patent Annuities (default)')->update([
            'default_official_fee' => 150,
            'default_service_fee' => 75,
            'currency' => 'GBP',
            'grace_months' => 3,
        ]);

        $matter = Matter::factory()->create([
            'country_code' => 'GB',
            'application_date' => now()->subMonths(6),
        ]);

        app(RenewalScheduler::class)->generate($matter);

        $renewal = $matter->renewals()->orderBy('cycle')->first();
        $this->assertSame('150.00', $renewal->official_fee);
        $this->assertSame('75.00', $renewal->service_fee);
        $this->assertSame('GBP', $renewal->currency);
        $this->assertSame(
            $renewal->due_date->copy()->addMonths(3)->toDateString(),
            $renewal->grace_date->toDateString()
        );
    }

    public function test_rules_index_is_displayed_with_summaries(): void
    {
        $this->actingAs($this->user)
            ->get(route('renewal-rules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RenewalRules/Index')
                ->has('rules', 8)
                ->where('rules.0.summary', fn ($s) => is_string($s) && $s !== ''));
    }

    public function test_regular_rule_can_be_created(): void
    {
        $this->actingAs($this->user)->post(route('renewal-rules.store'), [
            'name' => 'JP Patent Annuities',
            'matter_type' => 'patent',
            'country_code' => 'jp',
            'base_date' => 'registration',
            'schedule_mode' => 'regular',
            'start_cycle' => 1,
            'end_cycle' => 20,
            'interval_years' => 1,
            'grace_months' => 6,
            'is_active' => true,
        ])->assertRedirect(route('renewal-rules.index'));

        $rule = RenewalRule::firstWhere('name', 'JP Patent Annuities');
        $this->assertSame('JP', $rule->country_code);
        $this->assertNull($rule->offsets_months);
    }

    public function test_fixed_offsets_rule_can_be_created(): void
    {
        $this->actingAs($this->user)->post(route('renewal-rules.store'), [
            'name' => 'CA Patent Maintenance',
            'matter_type' => 'patent',
            'country_code' => 'CA',
            'base_date' => 'application',
            'schedule_mode' => 'fixed',
            'offsets_months' => [24, 36, 48],
            'grace_months' => 12,
            'is_active' => true,
        ]);

        $rule = RenewalRule::firstWhere('name', 'CA Patent Maintenance');
        $this->assertSame([24, 36, 48], $rule->offsets_months);
        $this->assertNull($rule->start_cycle);
    }

    public function test_duplicate_type_country_pair_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('renewal-rules.store'), [
            'name' => 'Duplicate US Patent Rule',
            'matter_type' => 'patent',
            'country_code' => 'US',
            'base_date' => 'registration',
            'schedule_mode' => 'regular',
            'start_cycle' => 1,
            'end_cycle' => 5,
            'interval_years' => 1,
            'grace_months' => 6,
        ])->assertSessionHasErrors('country_code');
    }

    public function test_rule_can_be_updated_and_deleted(): void
    {
        $rule = RenewalRule::firstWhere('country_code', 'EP');

        $this->actingAs($this->user)->patch(route('renewal-rules.update', $rule), [
            'name' => $rule->name,
            'matter_type' => 'patent',
            'country_code' => 'EP',
            'base_date' => 'application',
            'schedule_mode' => 'regular',
            'start_cycle' => 3,
            'end_cycle' => 20,
            'interval_years' => 1,
            'grace_months' => 4,
            'is_active' => true,
        ])->assertRedirect(route('renewal-rules.index'));

        $this->assertSame(4, $rule->fresh()->grace_months);

        $this->actingAs($this->user)->delete(route('renewal-rules.destroy', $rule));
        $this->assertDatabaseMissing('renewal_rules', ['id' => $rule->id]);
    }

    public function test_matter_page_reports_the_governing_rule(): void
    {
        $matter = Matter::factory()->create(['country_code' => 'US']);

        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertInertia(fn ($page) => $page
                ->where('renewalRule.name', 'US Patent Maintenance Fees'));
    }
}
