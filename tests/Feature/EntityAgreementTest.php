<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityAgreementTest extends TestCase
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

    private function logTime(Matter $matter, int $minutes = 60): void
    {
        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => $minutes,
        ]);
    }

    public function test_an_entity_default_agreement_governs_its_matters(): void
    {
        $entity = $this->client->entities()->first();

        $this->actingAs($this->user)->post(route('entities.agreement.save', $entity), [
            'type' => 'blended',
            'blended_rate' => 225,
            'increment_minutes' => 15,
        ])->assertSessionHasNoErrors();

        // The matter has no agreement of its own — the entity default applies
        $matter = Matter::factory()->create(['client_id' => $this->client->id]);
        $this->logTime($matter, 16);

        $entry = $matter->timeEntries()->first();
        $this->assertSame(225.0, $entry->rate);     // blended, not the rate card
        $this->assertSame(30, $entry->billed_minutes); // entity's 15-minute blocks
    }

    public function test_a_matter_override_beats_the_entity_default(): void
    {
        $entity = $this->client->entities()->first();
        $entity->billingAgreement()->create(['type' => 'blended', 'blended_rate' => 225]);

        $matter = Matter::factory()->create(['client_id' => $this->client->id]);
        $matter->billingAgreement()->create(['type' => 'hourly', 'increment_minutes' => 6]);

        $this->logTime($matter);

        // Hourly override: the rate card wins, not the entity's blended rate
        $this->assertSame(100.0, $matter->timeEntries()->first()->rate);
    }

    public function test_removing_the_override_falls_back_to_the_entity_default(): void
    {
        $entity = $this->client->entities()->first();
        $entity->billingAgreement()->create(['type' => 'blended', 'blended_rate' => 225]);

        $matter = Matter::factory()->create(['client_id' => $this->client->id]);
        $matter->billingAgreement()->create(['type' => 'hourly']);

        $this->actingAs($this->user)
            ->delete(route('matters.agreement.destroy', $matter))
            ->assertSessionHas('success');

        $this->assertSame('blended', $matter->fresh()->effectiveBillingAgreement()->type->value);
    }

    public function test_entity_defaults_cannot_be_stage_agreements(): void
    {
        $entity = $this->client->entities()->first();

        $this->actingAs($this->user)->post(route('entities.agreement.save', $entity), [
            'type' => 'stage',
            'increment_minutes' => 6,
            'stages' => [['description' => 'Filing', 'amount' => 100]],
        ])->assertSessionHas('error');

        $this->assertNull($entity->fresh()->billingAgreement);
    }

    public function test_a_fixed_entity_default_keeps_time_off_invoices(): void
    {
        $entity = $this->client->entities()->first();
        $entity->billingAgreement()->create(['type' => 'fixed', 'fixed_amount' => 900]);

        $matter = Matter::factory()->create(['client_id' => $this->client->id]);
        $this->logTime($matter);
        $this->actingAs($this->user)->post(route('matters.charges.store', $matter), [
            'type' => 'fixed_fee', 'date' => '2026-06-01',
            'description' => 'Fixed fee', 'amount' => 900,
        ]);

        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));

        $invoice = Invoice::first();
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame(900.0, $invoice->subtotal);
        $this->assertSame('billable', $matter->timeEntries()->first()->status->value);
    }

    public function test_matter_page_reports_the_entity_currency(): void
    {
        $entity = $this->client->entities()->first();
        $entity->update(['currency_code' => 'USD']);
        $entity->billingAgreement()->create(['type' => 'blended', 'blended_rate' => 300]);

        $matter = Matter::factory()->create([
            'client_id' => $this->client->id,
            'client_entity_id' => $entity->id,
        ]);

        // Regression: the show page must expose the entity's real currency,
        // not the base-currency fallback from a partial eager load.
        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertInertia(fn ($page) => $page
                ->where('billing.currency', 'USD')
                ->where('billingAgreement.type', 'blended'));
    }

    public function test_matter_page_reports_the_agreement_source(): void
    {
        $entity = $this->client->entities()->first();
        $entity->billingAgreement()->create(['type' => 'capped', 'cap_amount' => 5000]);

        $inherited = Matter::factory()->create(['client_id' => $this->client->id]);
        $overridden = Matter::factory()->create(['client_id' => $this->client->id]);
        $overridden->billingAgreement()->create(['type' => 'hourly']);

        $this->actingAs($this->user)
            ->get(route('matters.show', $inherited))
            ->assertInertia(fn ($page) => $page
                ->where('billingAgreementSource', 'entity')
                ->where('billingAgreement.type', 'capped'));

        $this->actingAs($this->user)
            ->get(route('matters.show', $overridden))
            ->assertInertia(fn ($page) => $page
                ->where('billingAgreementSource', 'matter')
                ->where('billingAgreement.type', 'hourly'));
    }
}
