<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->matter = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 100, 'effective_from' => '2020-01-01']);
    }

    public function test_budgets_record_creator_time_and_currency(): void
    {
        $this->actingAs($this->user)->post(route('matters.budgets.store', $this->matter), [
            'amount' => 1500, 'description' => 'Prosecution budget',
        ])->assertSessionHas('success');

        $budget = Budget::first();
        $this->assertSame($this->user->id, $budget->created_by);
        $this->assertSame('GBP', $budget->currency_code); // matter billing ccy default
        $this->assertSame('1500.00', $budget->amount);
        $this->assertSame('1500.00', $budget->base_amount);
        $this->assertNotNull($budget->created_at);
    }

    public function test_budgets_accumulate_and_track_consumption_of_billed_and_wip(): void
    {
        $this->actingAs($this->user)->post(route('matters.budgets.store', $this->matter), ['amount' => 1000]);
        $this->actingAs($this->user)->post(route('matters.budgets.store', $this->matter), ['amount' => 500]);

        // WIP time (100) + a billed charge (300) both consume the budget
        $this->actingAs($this->user)->post(route('matters.time.store', $this->matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);
        $this->matter->charges()->create([
            'type' => 'fixed_fee', 'date' => '2026-06-01', 'description' => 'Billed fee',
            'amount' => 300, 'base_amount' => 300, 'currency_code' => 'GBP', 'status' => 'billed',
        ]);
        // Written-off work does NOT consume
        $this->matter->charges()->create([
            'type' => 'other', 'date' => '2026-06-01', 'description' => 'Written off',
            'amount' => 999, 'base_amount' => 999, 'currency_code' => 'GBP', 'status' => 'written_off',
        ]);

        $this->actingAs($this->user)
            ->get(route('matters.show', $this->matter))
            ->assertInertia(fn ($page) => $page
                ->where('billingBudget.budget', 1500)
                ->where('billingBudget.consumed', 400)
                ->where('billingBudget.utilisation', 26.7)
                ->has('billingBudget.rows', 2));
    }

    public function test_amending_keeps_the_audit_trail(): void
    {
        $this->actingAs($this->user)->post(route('matters.budgets.store', $this->matter), ['amount' => 1000]);
        $budget = Budget::first();
        $originalCreator = $budget->created_by;

        $this->travel(1)->days();
        $this->actingAs(User::factory()->create())
            ->patch(route('budgets.update', $budget), ['amount' => 1250, 'description' => 'Revised'])
            ->assertSessionHas('success');

        $budget->refresh();
        $this->assertSame('1250.00', $budget->amount);
        $this->assertSame($originalCreator, $budget->created_by); // creator preserved
        $this->assertTrue($budget->updated_at->gt($budget->created_at));
    }

    public function test_budgets_in_foreign_currency_store_a_base_value(): void
    {
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 1.25, 'rate_date' => '2026-01-01']);

        $this->actingAs($this->user)->post(route('matters.budgets.store', $this->matter), [
            'amount' => 1250, 'currency_code' => 'EUR',
        ]);

        $this->assertSame('1000.00', Budget::first()->base_amount);
    }

    public function test_the_budget_dashboard_defaults_to_my_portfolio(): void
    {
        $mine = Matter::factory()->create([
            'client_id' => $this->matter->client_id,
            'responsible_user_id' => $this->user->id,
        ]);
        $mine->budgets()->create([
            'created_by' => $this->user->id, 'amount' => 800,
            'currency_code' => 'GBP', 'base_amount' => 800,
        ]);
        // Someone else's budgeted matter
        $this->matter->update(['responsible_user_id' => User::factory()->create()->id]);
        $this->matter->budgets()->create([
            'created_by' => $this->user->id, 'amount' => 2000,
            'currency_code' => 'GBP', 'base_amount' => 2000,
        ]);

        // Default view: just my matters
        $this->actingAs($this->user)
            ->get(route('budgets.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Budgets')
                ->has('rows', 1)
                ->where('rows.0.reference', $mine->reference)
                ->where('rows.0.budget', 800));

        // Cleared filter: the whole firm
        $this->actingAs($this->user)
            ->get(route('budgets.index', ['user_id' => '']))
            ->assertInertia(fn ($page) => $page->has('rows', 2));
    }

    public function test_the_main_dashboard_wip_tile_is_scoped_to_the_user(): void
    {
        $mine = Matter::factory()->create([
            'client_id' => $this->matter->client_id,
            'responsible_user_id' => $this->user->id,
        ]);
        $theirs = Matter::factory()->create([
            'client_id' => $this->matter->client_id,
            'responsible_user_id' => User::factory()->create()->id,
        ]);

        foreach ([$mine, $theirs] as $matter) {
            $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
                'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.myWipBase', 100) // only my matter's 100, not 200
                ->where('stats.baseCurrency', 'GBP'));
    }
}
