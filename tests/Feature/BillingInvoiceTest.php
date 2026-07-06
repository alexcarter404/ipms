<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 100, 'effective_from' => '2020-01-01']);
    }

    private function matter(array $agreement = ['type' => 'hourly']): Matter
    {
        $matter = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);
        $matter->billingAgreement()->create($agreement);

        return $matter;
    }

    private function logTime(Matter $matter, int $minutes, string $date = '2026-06-01'): void
    {
        $this->actingAs($this->user)->post(route('matters.time.store', $matter), [
            'user_id' => $this->user->id, 'work_date' => $date, 'minutes' => $minutes,
        ]);
    }

    public function test_drafting_an_invoice_gathers_unbilled_wip_and_snapshots_tax(): void
    {
        $vat = TaxRate::create(['name' => 'UK VAT', 'rate' => 20, 'is_default' => true]);
        $matter = $this->matter();
        $matter->effectiveBillingEntity()->update(['tax_rate_id' => $vat->id]);

        $this->logTime($matter, 60); // 100.00
        $this->actingAs($this->user)->post(route('matters.disbursements.store', $matter), [
            'date' => '2026-06-02', 'description' => 'Official fee',
            'cost_amount' => 50, 'cost_currency' => 'GBP',
        ]);

        $this->actingAs($this->user)
            ->post(route('matters.invoices.store', $matter))
            ->assertRedirect();

        $invoice = Invoice::first();
        $this->assertSame('draft', $invoice->status->value);
        $this->assertSame(2, $invoice->lines()->count());
        $this->assertSame(150.0, $invoice->subtotal);
        $this->assertSame(30.0, $invoice->tax_amount);   // 20% VAT snapshot
        $this->assertSame(180.0, $invoice->total);
        $this->assertSame('UK VAT', $invoice->tax_name);

        // The WIP is locked to the invoice
        $this->assertSame('billed', $matter->timeEntries()->first()->status->value);
        $this->assertSame('billed', $matter->disbursements()->first()->status->value);

        // Nothing left to bill — a second draft is refused
        $this->actingAs($this->user)
            ->post(route('matters.invoices.store', $matter))
            ->assertSessionHas('error');
    }

    public function test_fixed_fee_matters_bill_charges_but_never_time(): void
    {
        $matter = $this->matter(['type' => 'fixed', 'fixed_amount' => 2000]);

        $this->logTime($matter, 120); // tracked, but not billable under a fixed fee
        $this->actingAs($this->user)->post(route('matters.charges.store', $matter), [
            'type' => 'fixed_fee', 'date' => '2026-06-01',
            'description' => 'Fixed fee — agreed scope', 'amount' => 2000,
        ]);

        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));

        $invoice = Invoice::first();
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame(2000.0, $invoice->subtotal);
        $this->assertSame('billable', $matter->timeEntries()->first()->status->value);
    }

    public function test_capped_agreements_get_a_cap_adjustment_line(): void
    {
        $matter = $this->matter(['type' => 'capped', 'cap_amount' => 150]);

        $this->logTime($matter, 60, '2026-06-01'); // 100
        $this->logTime($matter, 60, '2026-06-02'); // 100 → 50 over cap

        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));

        $invoice = Invoice::first();
        $adjustment = $invoice->lines()->where('line_total', '<', 0)->first();
        $this->assertNotNull($adjustment);
        $this->assertSame(-50.0, $adjustment->line_total);
        $this->assertSame(150.0, $invoice->subtotal); // capped exactly
    }

    public function test_issuing_assigns_a_sequential_number_and_terms(): void
    {
        $matter = $this->matter();
        $this->logTime($matter, 60);
        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));

        $invoice = Invoice::first();
        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));

        $invoice->refresh();
        $this->assertSame('INV-'.now()->year.'-0001', $invoice->invoice_no);
        $this->assertSame('issued', $invoice->status->value);
        $this->assertTrue($invoice->due_at->equalTo(now()->startOfDay()->addDays(30)));

        // Issuing twice is refused
        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))
            ->assertSessionHas('error');
    }

    public function test_payments_settle_the_invoice_when_the_balance_reaches_zero(): void
    {
        $matter = $this->matter();
        $this->logTime($matter, 120); // 200.00
        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));
        $invoice = Invoice::first();

        // No payments against drafts
        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'date' => '2026-06-10', 'amount' => 50, 'method' => 'bank_transfer',
        ])->assertSessionHas('error');

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));

        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'date' => '2026-06-10', 'amount' => 50, 'method' => 'bank_transfer',
        ]);
        $this->assertSame('issued', $invoice->fresh()->status->value);

        $this->actingAs($this->user)->post(route('invoices.payments.store', $invoice), [
            'date' => '2026-06-20', 'amount' => 150, 'method' => 'card',
        ]);
        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame(0.0, $invoice->fresh()->balance());
    }

    public function test_voiding_an_issued_invoice_releases_its_wip(): void
    {
        $matter = $this->matter();
        $this->logTime($matter, 60);
        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));
        $invoice = Invoice::first();
        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));

        $this->actingAs($this->user)->post(route('invoices.void', $invoice));

        $entry = $matter->timeEntries()->first();
        $this->assertSame('void', $invoice->fresh()->status->value);
        $this->assertSame('billable', $entry->status->value);
        $this->assertNull($entry->invoice_line_id);
    }

    public function test_deleting_a_draft_releases_its_wip(): void
    {
        $matter = $this->matter();
        $this->logTime($matter, 60);
        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));
        $invoice = Invoice::first();

        $this->actingAs($this->user)->delete(route('invoices.destroy', $invoice));

        $this->assertNull(Invoice::find($invoice->id));
        $this->assertSame('billable', $matter->timeEntries()->first()->status->value);

        // Issued invoices cannot be deleted
        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));
        $second = Invoice::first();
        $this->actingAs($this->user)->post(route('invoices.issue', $second));
        $this->actingAs($this->user)->delete(route('invoices.destroy', $second))
            ->assertSessionHas('error');
    }

    public function test_invoice_pages_render(): void
    {
        $matter = $this->matter();
        $this->logTime($matter, 60);
        $this->actingAs($this->user)->post(route('matters.invoices.store', $matter));
        $invoice = Invoice::first();

        $this->actingAs($this->user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Index')
                ->has('invoices.data', 1));

        $this->actingAs($this->user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Invoices/Show')
                ->has('invoice.lines', 1)
                ->where('invoice.display_number', "Draft #{$invoice->id}"));
    }
}
