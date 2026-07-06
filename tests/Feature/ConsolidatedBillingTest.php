<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 100, 'effective_from' => '2020-01-01']);
    }

    private function matterWithTime(int $minutes, array $attributes = []): Matter
    {
        $matter = Matter::factory()->create(['client_id' => $this->client->id] + $attributes);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => $minutes,
        ]);

        return $matter;
    }

    public function test_an_entity_level_draft_consolidates_matters_billed_to_it(): void
    {
        $vat = TaxRate::create(['name' => 'UK VAT', 'rate' => 20, 'is_default' => true]);
        $entity = $this->client->entities()->first();
        $entity->update(['tax_rate_id' => $vat->id]);

        $first = $this->matterWithTime(60);   // 100.00, default entity
        $second = $this->matterWithTime(120); // 200.00, default entity

        // A sibling entity's matter must NOT be swept up
        $other = $this->client->entities()->create(['name' => 'Overseas arm']);
        $foreign = $this->matterWithTime(60, ['client_entity_id' => $other->id]);

        $this->actingAs($this->user)
            ->post(route('entities.invoices.store', $entity))
            ->assertRedirect();

        $invoice = Invoice::first();
        $this->assertNull($invoice->matter_id); // consolidated
        $this->assertSame(300.0, $invoice->subtotal);
        $this->assertSame(360.0, $invoice->total); // +20% VAT
        $this->assertSame(
            [$first->id, $second->id],
            $invoice->lines->pluck('matter_id')->unique()->sort()->values()->all()
        );

        // The other entity's WIP is untouched
        $this->assertSame('billable', $foreign->timeEntries()->first()->status->value);
    }

    public function test_a_consolidated_draft_can_bill_a_subset_of_matters(): void
    {
        $entity = $this->client->entities()->first();
        $wanted = $this->matterWithTime(60);
        $excluded = $this->matterWithTime(60);

        $this->actingAs($this->user)->post(route('entities.invoices.store', $entity), [
            'matter_ids' => [$wanted->id],
        ]);

        $invoice = Invoice::first();
        $this->assertSame([$wanted->id], $invoice->lines->pluck('matter_id')->unique()->all());
        $this->assertSame('billable', $excluded->timeEntries()->first()->status->value);
    }

    public function test_consolidated_drafts_convert_each_matter_into_the_entity_currency(): void
    {
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 1.25, 'rate_date' => '2026-01-01']);
        $entity = $this->client->entities()->first();
        $entity->update(['currency_code' => 'EUR']);

        // Matter agreement pins GBP, so its WIP sits in GBP…
        $matter = Matter::factory()->create(['client_id' => $this->client->id]);
        $matter->billingAgreement()->create(['type' => 'hourly', 'currency_code' => 'GBP']);
        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);
        $this->assertSame('GBP', $matter->timeEntries()->first()->currency_code);

        // …but the entity's consolidated invoice is EUR
        $this->actingAs($this->user)->post(route('entities.invoices.store', $entity));

        $invoice = Invoice::first();
        $this->assertSame('EUR', $invoice->currency_code);
        $this->assertSame(125.0, $invoice->subtotal); // 100 GBP × 1.25
    }

    public function test_an_entity_with_no_unbilled_work_cannot_be_billed(): void
    {
        $entity = $this->client->entities()->first();

        $this->actingAs($this->user)
            ->post(route('entities.invoices.store', $entity))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::count());
    }

    public function test_the_wip_dashboard_summarises_each_entity_with_age(): void
    {
        $this->matterWithTime(60);  // 100, dated 2026-06-01
        $this->matterWithTime(120); // 200

        // Fixed-fee matter: time is tracked but not billable
        $fixed = Matter::factory()->create(['client_id' => $this->client->id]);
        $fixed->billingAgreement()->create(['type' => 'fixed', 'fixed_amount' => 500]);
        $this->actingAs($this->user)->post(route('matters.time.store', $fixed), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);

        $this->actingAs($this->user)
            ->get(route('billing.wip'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Wip')
                ->has('rows', 1)
                ->where('rows.0.entity.name', $this->client->entities()->first()->name)
                ->where('rows.0.matter_count', 3)
                ->where('rows.0.total', 300) // fixed matter's time excluded
                ->where('rows.0.oldest_date', '2026-06-01')
                ->has('rows.0.oldest_days')
                ->has('clients')
                ->has('users'));
    }

    public function test_the_wip_dashboard_filters_by_responsible_attorney(): void
    {
        $this->matterWithTime(60, ['responsible_user_id' => $this->user->id]);
        $someoneElse = User::factory()->create();
        $this->matterWithTime(60, ['responsible_user_id' => $someoneElse->id]);

        $this->actingAs($this->user)
            ->get(route('billing.wip', ['user_id' => $this->user->id]))
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.matter_count', 1));
    }

    public function test_the_entity_drill_in_lists_each_wip_item(): void
    {
        $entity = $this->client->entities()->first();
        $matter = $this->matterWithTime(60);
        $this->actingAs($this->user)->post(route('matters.disbursements.store', $matter), [
            'date' => '2026-06-05', 'description' => 'Courier to agent',
            'cost_amount' => 40, 'cost_currency' => 'GBP',
        ]);

        $this->actingAs($this->user)
            ->get(route('billing.wip.show', $entity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/WipEntity')
                ->where('wip.entity.id', $entity->id)
                ->has('wip.matters', 1)
                ->has('wip.matters.0.items', 2)
                ->where('wip.matters.0.items.1.description', 'Courier to agent')
                ->where('wip.total', 140));
    }

    public function test_wip_descriptions_can_be_amended_until_billed(): void
    {
        $matter = $this->matterWithTime(60);
        $entry = $matter->timeEntries()->first();
        $this->actingAs($this->user)->post(route('matters.charges.store', $matter), [
            'type' => 'other', 'date' => '2026-06-01',
            'description' => 'Filing fee', 'amount' => 50,
        ]);
        $charge = $matter->charges()->first();

        $this->actingAs($this->user)
            ->patch(route('time-entries.update', $entry), ['narrative' => 'Reviewed examiner citations'])
            ->assertSessionHas('success');
        $this->assertSame('Reviewed examiner citations', $entry->fresh()->narrative);

        $this->actingAs($this->user)
            ->patch(route('charges.update', $charge), ['description' => 'Official filing fee'])
            ->assertSessionHas('success');
        $this->assertSame('Official filing fee', $charge->fresh()->description);

        // Once billed, wording is locked to the invoice
        $entry->update(['status' => 'billed']);
        $this->actingAs($this->user)
            ->patch(route('time-entries.update', $entry), ['narrative' => 'Too late'])
            ->assertSessionHas('error');
        $this->assertSame('Reviewed examiner citations', $entry->fresh()->narrative);
    }

    public function test_consolidated_invoice_show_loads_line_matters_for_grouping(): void
    {
        $this->matterWithTime(60);
        $this->matterWithTime(60);
        $entity = $this->client->entities()->first();

        $this->actingAs($this->user)->post(route('entities.invoices.store', $entity));
        $invoice = Invoice::first();

        $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Show')
                ->where('invoice.matter_id', null)
                ->has('invoice.lines', 2)
                ->has('invoice.lines.0.matter.reference'));
    }
}
