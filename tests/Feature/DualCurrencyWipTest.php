<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualCurrencyWipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 300, 'effective_from' => '2020-01-01']);
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 1.20, 'rate_date' => '2026-01-01']);
    }

    private function eurMatter(): Matter
    {
        $this->client->entities()->first()->update(['currency_code' => 'EUR']);

        return Matter::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_time_stores_billing_and_base_amounts_at_capture(): void
    {
        $matter = $this->eurMatter();

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);

        $entry = $matter->timeEntries()->first();
        $this->assertSame('360.00', $entry->amount);      // 300 GBP → EUR at 1.20
        $this->assertSame('EUR', $entry->currency_code);
        $this->assertSame('300.00', $entry->base_amount); // back at the same day's rate
    }

    public function test_disbursements_and_charges_store_base_amounts(): void
    {
        $matter = $this->eurMatter();

        $this->actingAs($this->user)->post(route('matters.disbursements.store', $matter), [
            'date' => '2026-06-01', 'description' => 'EUIPO fee',
            'cost_amount' => 120, 'cost_currency' => 'EUR', 'markup_pct' => 0,
        ]);
        $this->actingAs($this->user)->post(route('matters.charges.store', $matter), [
            'type' => 'fixed_fee', 'date' => '2026-06-01',
            'description' => 'Fixed fee', 'amount' => 600,
        ]);

        $this->assertSame('100.00', $matter->disbursements()->first()->base_amount); // 120 EUR / 1.2
        $this->assertSame('500.00', $matter->charges()->first()->base_amount);       // 600 EUR / 1.2
    }

    public function test_base_amounts_are_frozen_against_later_fx_moves(): void
    {
        $matter = $this->eurMatter();

        $this->actingAs($this->user)->post(route('matters.charges.store', $matter), [
            'type' => 'fixed_fee', 'date' => '2026-06-01',
            'description' => 'Fixed fee', 'amount' => 600,
        ]);

        // The rate moves after capture — stored values must not.
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 2.00, 'rate_date' => '2026-06-02']);

        $this->assertSame('500.00', $matter->charges()->first()->base_amount);

        $this->actingAs($this->user)
            ->get(route('billing.wip'))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.total', 600)        // entity currency (EUR)
                ->where('rows.0.base_total', 500)   // capture-date base value
                ->where('firmTotal', 500)
                ->where('baseCurrency', 'GBP'));
    }

    public function test_wip_screens_expose_base_totals(): void
    {
        $matter = $this->eurMatter();
        $entity = $this->client->entities()->first();

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);

        $this->actingAs($this->user)
            ->get(route('billing.wip.show', $entity))
            ->assertInertia(fn ($page) => $page
                ->where('wip.total', 360)
                ->where('wip.total_base', 300)
                ->where('baseCurrency', 'GBP'));

        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertInertia(fn ($page) => $page
                ->where('billing.wip.total', 360)
                ->where('billing.wip.base_total', 300)
                ->where('billing.wip.base_currency', 'GBP'));
    }

    public function test_base_currency_matters_store_identical_amounts(): void
    {
        $matter = Matter::factory()->create(['client_id' => $this->client->id]); // GBP entity

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);

        $entry = $matter->timeEntries()->first();
        $this->assertSame($entry->amount, $entry->base_amount);
    }
}
