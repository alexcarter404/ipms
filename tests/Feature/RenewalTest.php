<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\Renewal;
use App\Models\User;
use App\Services\RenewalScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_patent_schedule_generates_annuities_from_filing_date(): void
    {
        $matter = Matter::factory()->create([
            'application_date' => now()->subMonths(6),
        ]);

        $created = app(RenewalScheduler::class)->generate($matter);

        // years 2-20 from filing, all in the future
        $this->assertCount(19, $created);
        $this->assertSame(2, $matter->renewals()->orderBy('cycle')->first()->cycle);
        $this->assertEquals(
            $matter->application_date->copy()->addYears(2)->toDateString(),
            $matter->renewals()->orderBy('cycle')->first()->due_date->toDateString()
        );
    }

    public function test_trademark_schedule_generates_ten_year_cycles(): void
    {
        $matter = Matter::factory()->trademark()->create([
            'application_date' => now()->subYear(),
        ]);

        app(RenewalScheduler::class)->generate($matter);

        $first = $matter->renewals()->orderBy('cycle')->first();
        $this->assertEquals(
            $matter->application_date->copy()->addYears(10)->toDateString(),
            $first->due_date->toDateString()
        );
        $this->assertSame(5, $matter->renewals()->count());
    }

    public function test_generation_is_idempotent(): void
    {
        $matter = Matter::factory()->create(['application_date' => now()->subMonths(6)]);
        $scheduler = app(RenewalScheduler::class);

        $scheduler->generate($matter);
        $second = $scheduler->generate($matter);

        $this->assertCount(0, $second);
        $this->assertSame(19, $matter->renewals()->count());
    }

    public function test_generation_skips_long_past_and_post_expiry_renewals(): void
    {
        $matter = Matter::factory()->create([
            'application_date' => now()->subYears(5),
            'expiry_date' => now()->addYears(3),
        ]);

        app(RenewalScheduler::class)->generate($matter);

        $this->assertSame(0, $matter->renewals()->whereDate('due_date', '<', now()->subYear())->count());
        $this->assertSame(0, $matter->renewals()->whereDate('due_date', '>', $matter->expiry_date)->count());
    }

    public function test_matter_without_base_date_generates_nothing(): void
    {
        $matter = Matter::factory()->create(['application_date' => null]);

        $this->actingAs($this->user)
            ->from(route('matters.show', $matter))
            ->post(route('matters.renewals.generate', $matter))
            ->assertSessionHas('error');

        $this->assertSame(0, $matter->renewals()->count());
    }

    public function test_generate_endpoint_creates_schedule(): void
    {
        $matter = Matter::factory()->create(['application_date' => now()->subMonths(6)]);

        $this->actingAs($this->user)
            ->post(route('matters.renewals.generate', $matter))
            ->assertSessionHas('success');

        $this->assertGreaterThan(0, $matter->renewals()->count());
    }

    public function test_marking_instructed_and_paid_records_timestamps(): void
    {
        $renewal = Renewal::factory()->create();

        $this->actingAs($this->user)->patch(route('renewals.update', $renewal), ['status' => 'instructed']);
        $this->assertNotNull($renewal->fresh()->instructed_at);

        $this->actingAs($this->user)->patch(route('renewals.update', $renewal), ['status' => 'paid']);
        $renewal->refresh();
        $this->assertSame('paid', $renewal->status->value);
        $this->assertNotNull($renewal->paid_at);
    }

    public function test_renewals_index_filters_by_due_window(): void
    {
        Renewal::factory()->create(['due_date' => now()->addDays(10)]);
        Renewal::factory()->create(['due_date' => now()->addDays(200)]);

        $this->actingAs($this->user)
            ->get(route('renewals.index', ['due_within' => 30]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Renewals/Index')
                ->has('renewals.data', 1));
    }

    public function test_manual_renewal_cannot_duplicate_cycle(): void
    {
        $renewal = Renewal::factory()->create(['cycle' => 5]);

        $this->actingAs($this->user)
            ->post(route('matters.renewals.store', $renewal->matter_id), [
                'cycle' => 5,
                'due_date' => now()->addYear()->toDateString(),
                'currency' => 'USD',
            ])
            ->assertSessionHasErrors('cycle');
    }
}
