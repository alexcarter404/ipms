<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingQuoteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // --- Quotes ---

    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => Client::factory()->create()->id,
            'currency_code' => 'EUR',
            'lines' => [
                ['description' => 'Drafting and filing', 'quantity' => 1, 'unit_amount' => 2400],
                ['description' => 'Official fees', 'quantity' => 2, 'unit_amount' => 300],
            ],
        ], $overrides);
    }

    public function test_a_quote_is_numbered_and_totalled_with_tax(): void
    {
        $vat = TaxRate::create(['name' => 'UK VAT', 'rate' => 20, 'is_default' => true]);

        $this->actingAs($this->user)
            ->post(route('quotes.store'), $this->quotePayload(['tax_rate_id' => $vat->id]))
            ->assertRedirect();

        $quote = Quote::first();
        $this->assertSame('Q-'.now()->year.'-0001', $quote->quote_no);
        $this->assertSame('3000.00', $quote->subtotal); // 2400 + 2×300
        $this->assertSame('600.00', $quote->tax_amount);
        $this->assertSame('3600.00', $quote->total);
        $this->assertSame(2, $quote->lines()->count());
    }

    public function test_quotes_transition_draft_sent_accepted_and_lock(): void
    {
        $this->actingAs($this->user)->post(route('quotes.store'), $this->quotePayload());
        $quote = Quote::first();

        $this->actingAs($this->user)->patch(route('quotes.status', $quote), ['status' => 'sent']);
        $this->assertSame('sent', $quote->fresh()->status->value);

        $this->actingAs($this->user)->patch(route('quotes.status', $quote), ['status' => 'accepted']);
        $this->assertSame('accepted', $quote->fresh()->status->value);

        // Accepted quotes cannot move again or be edited
        $this->actingAs($this->user)
            ->patch(route('quotes.status', $quote), ['status' => 'declined'])
            ->assertSessionHas('error');

        $this->actingAs($this->user)
            ->patch(route('quotes.update', $quote), $this->quotePayload(['client_id' => $quote->client_id]))
            ->assertSessionHas('error');
    }

    public function test_quote_pages_render(): void
    {
        $this->actingAs($this->user)->post(route('quotes.store'), $this->quotePayload());
        $quote = Quote::first();

        $this->actingAs($this->user)
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Quotes/Index')
                ->has('quotes.data', 1));

        $this->actingAs($this->user)
            ->get(route('quotes.edit', $quote))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Quotes/Edit')
                ->has('quote.lines', 2)
                ->has('currencies')
                ->has('taxRates'));
    }

    // --- Settings ---

    public function test_settings_page_renders_reference_data(): void
    {
        TaxRate::create(['name' => 'UK VAT', 'rate' => 20, 'is_default' => true]);
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 1.17, 'rate_date' => '2026-07-01']);

        $this->actingAs($this->user)
            ->get(route('billing.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Settings')
                ->where('baseCurrency', 'GBP')
                ->has('exchangeRates', 1)
                ->has('taxRates', 1)
                ->has('rateCards')
                ->has('activityCodes'));
    }

    public function test_only_one_tax_rate_can_be_the_default(): void
    {
        $this->actingAs($this->user)->post(route('billing.tax-rates.store'), [
            'name' => 'UK VAT', 'rate' => 20, 'is_default' => true,
        ]);
        $this->actingAs($this->user)->post(route('billing.tax-rates.store'), [
            'name' => 'Zero-rated', 'rate' => 0, 'is_default' => true,
        ]);

        $this->assertSame(1, TaxRate::where('is_default', true)->count());
        $this->assertSame('Zero-rated', TaxRate::firstWhere('is_default', true)->name);
    }

    public function test_exchange_rates_upsert_per_currency_and_date(): void
    {
        $payload = ['currency_code' => 'EUR', 'rate' => 1.15, 'rate_date' => '2026-07-01'];

        $this->actingAs($this->user)->post(route('billing.exchange-rates.save'), $payload);
        $this->actingAs($this->user)->post(route('billing.exchange-rates.save'), ['rate' => 1.18] + $payload);

        $this->assertSame(1, ExchangeRate::count());
        $this->assertSame('1.180000', ExchangeRate::first()->rate);

        // The base currency itself is rejected
        $this->actingAs($this->user)
            ->post(route('billing.exchange-rates.save'), ['currency_code' => 'GBP'] + $payload)
            ->assertSessionHasErrors('currency_code');
    }

    public function test_sync_rates_command_stores_provider_rates(): void
    {
        Http::fake([
            '*' => Http::response([
                'base' => 'GBP',
                'date' => '2026-07-06',
                'rates' => ['EUR' => 1.166, 'USD' => 1.271],
            ]),
        ]);

        $this->artisan('billing:sync-rates')->assertSuccessful();

        $this->assertSame(2, ExchangeRate::count());
        $this->assertSame('1.166000', ExchangeRate::firstWhere('currency_code', 'EUR')->rate);

        // Re-running the same day updates rather than duplicates
        $this->artisan('billing:sync-rates')->assertSuccessful();
        $this->assertSame(2, ExchangeRate::count());
    }

    public function test_sync_failure_is_reported_not_fatal(): void
    {
        Http::fake(['*' => Http::response('unavailable', 503)]);

        $this->artisan('billing:sync-rates')->assertFailed();

        $this->actingAs($this->user)
            ->post(route('billing.sync-rates'))
            ->assertSessionHas('error');
    }

    public function test_entities_accept_currency_and_tax_assignment(): void
    {
        $vat = TaxRate::create(['name' => 'UK VAT', 'rate' => 20]);
        $client = Client::factory()->create();
        $entity = $client->entities()->first();

        $this->actingAs($this->user)->patch(route('entities.update', $entity), [
            'name' => $entity->name,
            'currency_code' => 'USD',
            'tax_rate_id' => $vat->id,
        ]);

        $entity->refresh();
        $this->assertSame('USD', $entity->currency_code);
        $this->assertSame($vat->id, $entity->tax_rate_id);
    }
}
