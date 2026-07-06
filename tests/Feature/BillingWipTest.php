<?php

namespace Tests\Feature;

use App\Models\ActivityCode;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingWipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function matter(array $agreement = []): Matter
    {
        $matter = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);

        if ($agreement) {
            $matter->billingAgreement()->create($agreement);
        }

        return $matter;
    }

    public function test_time_is_rounded_up_to_the_agreement_increment(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        $matter = $this->matter(['type' => 'hourly', 'increment_minutes' => 6]);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 50,
        ]);

        $entry = $matter->timeEntries()->first();
        $this->assertSame(50, $entry->minutes);
        $this->assertSame(54, $entry->billed_minutes); // next 6-minute block
        $this->assertSame('180.00', $entry->amount);   // 0.9h × 200
    }

    public function test_fifteen_minute_blocks_are_supported(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 100, 'effective_from' => '2020-01-01']);
        $matter = $this->matter(['type' => 'hourly', 'increment_minutes' => 15]);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 16,
        ]);

        $this->assertSame(30, $matter->timeEntries()->first()->billed_minutes);
    }

    public function test_the_most_specific_rate_card_wins(): void
    {
        $matter = $this->matter();

        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        RateCard::create(['user_id' => $this->user->id, 'currency_code' => 'GBP', 'hourly_rate' => 300, 'effective_from' => '2020-01-01']);
        RateCard::create(['client_id' => $matter->client_id, 'currency_code' => 'GBP', 'hourly_rate' => 250, 'effective_from' => '2020-01-01']);
        RateCard::create(['user_id' => $this->user->id, 'client_id' => $matter->client_id, 'currency_code' => 'GBP', 'hourly_rate' => 350, 'effective_from' => '2020-01-01']);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 60,
        ]);

        $this->assertSame('350.00', $matter->timeEntries()->first()->rate);
    }

    public function test_blended_agreements_apply_one_rate_to_every_timekeeper(): void
    {
        RateCard::create(['user_id' => $this->user->id, 'currency_code' => 'GBP', 'hourly_rate' => 400, 'effective_from' => '2020-01-01']);
        $matter = $this->matter(['type' => 'blended', 'blended_rate' => 225]);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 60,
        ]);

        $this->assertSame('225.00', $matter->timeEntries()->first()->rate);
    }

    public function test_rate_cards_convert_into_the_billing_currency(): void
    {
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 1.20, 'rate_date' => '2026-06-01']);
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 300, 'effective_from' => '2020-01-01']);

        $matter = $this->matter();
        $matter->effectiveBillingEntity()->update(['currency_code' => 'EUR']);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 60,
        ]);

        $entry = $matter->timeEntries()->first();
        $this->assertSame('EUR', $entry->currency_code);
        $this->assertSame('360.00', $entry->rate); // 300 GBP × 1.20
    }

    public function test_time_cannot_be_logged_without_any_rate_card(): void
    {
        $matter = $this->matter();

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 60,
        ])->assertSessionHas('error');

        $this->assertSame(0, $matter->timeEntries()->count());
    }

    public function test_task_based_billing_requires_an_activity_code(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        $matter = $this->matter(['type' => 'hourly', 'requires_task_codes' => true]);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 30,
        ])->assertSessionHasErrors('activity_code_id');

        $code = ActivityCode::create(['code' => 'P300', 'description' => 'Prosecution']);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id,
            'work_date' => '2026-06-01',
            'minutes' => 30,
            'activity_code_id' => $code->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_disbursements_are_marked_up_and_converted(): void
    {
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 1.20, 'rate_date' => '2026-06-01']);
        $matter = $this->matter(); // GBP billing

        $this->actingAs($this->user)->post(route('matters.disbursements.store', $matter), [
            'date' => '2026-06-01',
            'description' => 'EPO filing fee',
            'cost_amount' => 120,
            'cost_currency' => 'EUR',
            'markup_pct' => 20,
        ]);

        $disbursement = $matter->disbursements()->first();
        // 120 EUR × 1.2 markup = 144 EUR → GBP at 1.20 = 120.00
        $this->assertSame('120.00', $disbursement->amount);
        $this->assertSame('GBP', $disbursement->currency_code);
    }

    public function test_disbursement_markup_defaults_from_the_agreement(): void
    {
        $matter = $this->matter(['type' => 'hourly', 'default_markup_pct' => 10]);

        $this->actingAs($this->user)->post(route('matters.disbursements.store', $matter), [
            'date' => '2026-06-01',
            'description' => 'Courier',
            'cost_amount' => 50,
            'cost_currency' => 'GBP',
        ]);

        $this->assertSame('55.00', $matter->disbursements()->first()->amount);
    }

    public function test_a_stage_payment_is_raised_once_per_milestone(): void
    {
        $matter = $this->matter(['type' => 'stage']);
        $stage = $matter->billingAgreement->stages()->create([
            'description' => 'Filing complete', 'amount' => 1500, 'sort_order' => 0,
        ]);

        $this->actingAs($this->user)->post(route('agreement-stages.charge', $stage))
            ->assertSessionHas('success');

        $charge = $matter->charges()->first();
        $this->assertSame('1500.00', $charge->amount);
        $this->assertSame('stage_payment', $charge->type->value);

        // A second raise is refused
        $this->actingAs($this->user)->post(route('agreement-stages.charge', $stage))
            ->assertSessionHas('error');
        $this->assertSame(1, $matter->charges()->count());
    }

    public function test_billed_items_cannot_be_written_off_or_deleted(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        $matter = $this->matter();

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);
        $entry = $matter->timeEntries()->first();
        $entry->update(['status' => 'billed']);

        $this->actingAs($this->user)
            ->patch(route('time-entries.status', $entry), ['status' => 'written_off'])
            ->assertSessionHas('error');

        $this->actingAs($this->user)
            ->delete(route('time-entries.destroy', $entry))
            ->assertSessionHas('error');

        $this->assertNotNull($entry->fresh());
    }

    public function test_matter_page_exposes_billing_wip_totals(): void
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 100, 'effective_from' => '2020-01-01']);
        $matter = $this->matter(['type' => 'hourly']);

        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 60,
        ]);

        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertInertia(fn ($page) => $page
                ->component('Matters/Show')
                ->where('billing.wip.time', 100)
                ->where('billing.wip.total', 100)
                ->where('billing.currency', 'GBP')
                ->has('billingOptions.agreementTypes')
                ->has('billingAgreement'));
    }
}
