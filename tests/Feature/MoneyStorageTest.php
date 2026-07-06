<?php

namespace Tests\Feature;

use App\Actions\Billing\AddCharge;
use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use App\Repositories\WipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Money is fixed-point: integer minor units at rest, exact sums in SQL.
 */
class MoneyStorageTest extends TestCase
{
    use RefreshDatabase;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->matter = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);
    }

    public function test_money_lands_in_the_database_as_integer_minor_units(): void
    {
        app(AddCharge::class)->handle($this->matter, [
            'date' => '2026-06-01', 'type' => 'other',
            'description' => 'Fee', 'amount' => 123.45,
        ]);

        $raw = DB::table('charges')->first();

        $this->assertSame(12345, (int) $raw->amount);
        $this->assertSame(12345, (int) $raw->base_amount);
        // ...and reads back in major units through the cast
        $this->assertSame(123.45, $this->matter->charges()->first()->amount);
    }

    public function test_sums_of_awkward_fractions_are_exact(): void
    {
        // 0.10 + 0.20 + 0.30 is the classic float-drift case (0.1 + 0.2 != 0.3)
        foreach ([0.10, 0.20, 0.30] as $i => $amount) {
            app(AddCharge::class)->handle($this->matter, [
                'date' => '2026-06-0'.($i + 1), 'type' => 'other',
                'description' => "Micro fee {$i}", 'amount' => $amount,
            ]);
        }

        $totals = app(WipRepository::class)->totals($this->matter);

        $this->assertSame(0.6, $totals['charges']);
        $this->assertSame(60, (int) DB::table('charges')->sum('amount'));
    }
}
