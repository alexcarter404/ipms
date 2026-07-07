<?php

namespace Tests\Feature;

use App\Actions\Billing\AddCharge;
use App\Actions\Billing\LogTime;
use App\Mail\ScheduledReportMail;
use App\Models\Client;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\Report;
use App\Models\User;
use App\Services\InvoiceBuilder;
use App\Services\Invoicing\InvoicingProvider;
use App\Services\ReportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
        ]);
    }

    private function billedInvoice(): \App\Models\Invoice
    {
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 200, 'effective_from' => '2020-01-01']);
        app(LogTime::class)->handle($this->matter, [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01',
            'minutes' => 60, 'narrative' => 'Drafting response',
        ]);
        app(AddCharge::class)->handle($this->matter, [
            'date' => '2026-06-02', 'type' => 'other',
            'description' => 'Official fee', 'amount' => 120,
        ]);

        $invoice = app(InvoiceBuilder::class)->draft($this->matter);
        app(InvoicingProvider::class)->issue($invoice);

        return $invoice->fresh();
    }

    public function test_the_invoice_pdf_downloads(): void
    {
        $invoice = $this->billedInvoice();

        $response = $this->actingAs($this->user)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_ledes_1998b_export_types_fees_and_expenses(): void
    {
        $invoice = $this->billedInvoice();

        $response = $this->actingAs($this->user)
            ->get(route('invoices.ledes', $invoice))
            ->assertOk();

        $ledes = $response->getContent();
        $lines = array_values(array_filter(explode("\n", $ledes)));

        $this->assertSame('LEDES1998B[]', $lines[0]);
        $this->assertStringStartsWith('INVOICE_DATE|INVOICE_NUMBER|CLIENT_ID', $lines[1]);
        $this->assertCount(4, $lines); // header, fields, one fee, one expense

        $fee = collect($lines)->first(fn ($line) => str_contains($line, 'Drafting response'));
        $expense = collect($lines)->first(fn ($line) => str_contains($line, 'Official fee'));
        $this->assertSame('F', explode('|', $fee)[9]);
        $this->assertSame('E', explode('|', $expense)[9]);
        $this->assertSame('200.00', explode('|', $fee)[12]); // 60 min at 200/h
        $this->assertSame($this->matter->reference, explode('|', $fee)[3]);
        $this->assertSame($this->user->name, explode('|', $fee)[21]);
    }

    public function test_reports_run_with_filters(): void
    {
        $other = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);

        $result = app(ReportRunner::class)->run('matters', ['client_id' => $this->matter->client_id]);

        $this->assertSame('Reference', $result['headers'][0]);
        $this->assertCount(1, $result['rows']);
        $this->assertSame($this->matter->reference, $result['rows'][0][0]);

        // The WIP dataset spans time, disbursements and charges
        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 100, 'effective_from' => '2020-01-01']);
        app(LogTime::class)->handle($this->matter, [
            'user_id' => $this->user->id, 'work_date' => '2026-06-01', 'minutes' => 30,
        ]);
        $wip = app(ReportRunner::class)->run('wip', []);
        $this->assertCount(1, $wip['rows']);
        $this->assertSame('time', $wip['rows'][0][1]);
    }

    public function test_reports_are_saved_run_and_exported_from_the_page(): void
    {
        $this->actingAs($this->user)
            ->post(route('reports.store'), [
                'name' => 'My matters', 'type' => 'matters',
                'filters' => ['client_id' => $this->matter->client_id],
                'schedule' => 'daily',
            ])
            ->assertSessionHas('success');
        $this->assertSame(1, Report::count());

        $this->actingAs($this->user)
            ->get(route('reports.index', ['type' => 'matters']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('saved', 1)
                ->has('results.rows', 1)
                ->where('saved.0.schedule', 'daily'));

        $csv = $this->actingAs($this->user)
            ->get(route('reports.csv', ['type' => 'matters']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($this->matter->reference, $csv->getContent());
    }

    public function test_scheduled_reports_are_emailed_as_csv(): void
    {
        Mail::fake();

        Report::create([
            'name' => 'Daily docket', 'type' => 'matters',
            'filters' => [], 'schedule' => 'daily', 'created_by' => $this->user->id,
        ]);
        Report::create([
            'name' => 'Manual only', 'type' => 'matters',
            'filters' => [], 'schedule' => null, 'created_by' => $this->user->id,
        ]);

        Artisan::call('reports:send');

        Mail::assertSent(ScheduledReportMail::class, 1);
        Mail::assertSent(ScheduledReportMail::class, fn (ScheduledReportMail $mail) => $mail->hasTo($this->user->email)
            && $mail->report->name === 'Daily docket'
            && $mail->rowCount === 1);
        $this->assertNotNull(Report::firstWhere('name', 'Daily docket')->last_run_at);
    }
}
