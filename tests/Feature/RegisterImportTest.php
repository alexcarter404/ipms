<?php

namespace Tests\Feature;

use App\Http\Integrations\OfficeExchange\Requests\LookupRegisterRequest;
use App\Models\Client;
use App\Models\Matter;
use App\Models\RegisterCheck;
use App\Models\User;
use App\Services\Integrations\RegisterReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class RegisterImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    private function seedRegister(array $records, string $office = 'epo'): void
    {
        Storage::disk('local')->put("ipo-register/{$office}.json", json_encode($records));
    }

    public function test_a_matter_is_imported_from_the_office_register(): void
    {
        $client = Client::factory()->create();
        $this->seedRegister([
            'EP24555001.1' => [
                'title' => 'Adaptive haptic feedback allocator',
                'matter_type' => 'patent',
                'country_code' => 'EP',
                'filing_route' => 'ep',
                'status' => 'under_examination',
                'application_no' => 'EP24555001.1',
                'application_date' => '2025-05-04',
                'publication_no' => 'EP4477001',
            ],
        ]);

        $this->actingAs($this->user)
            ->post(route('matters.import'), [
                'office' => 'epo', 'application_no' => 'EP 24 555 001.1', 'client_id' => $client->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $matter = Matter::latest('id')->first();
        $this->assertSame('Adaptive haptic feedback allocator', $matter->title);
        $this->assertSame('EP24555001.1', $matter->application_no);
        $this->assertSame('EP4477001', $matter->publication_no);
        $this->assertSame('under_examination', $matter->status->value);
        $this->assertSame($client->id, $matter->client_id);
        $this->assertSame(sprintf('P-%d-0001', now()->year), $matter->reference);

        // The sequence advances per import
        $this->actingAs($this->user)->post(route('matters.import'), [
            'office' => 'epo', 'application_no' => 'EP24555001.1', 'client_id' => $client->id,
        ]);
        $this->assertSame(sprintf('P-%d-0002', now()->year), Matter::latest('id')->first()->reference);
    }

    public function test_unknown_numbers_are_refused_with_a_clear_error(): void
    {
        $this->seedRegister([]);

        $this->actingAs($this->user)
            ->post(route('matters.import'), [
                'office' => 'epo', 'application_no' => 'EP99999999.9',
                'client_id' => Client::factory()->create()->id,
            ])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'no record of EP99999999.9'));

        $this->assertSame(0, Matter::count());
    }

    public function test_reconciliation_flags_drift_against_the_register(): void
    {
        $matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'country_code' => 'EP',
            'application_no' => 'EP21789012.3',
            'publication_no' => null,
        ]);
        $clean = Matter::factory()->create([
            'client_id' => $matter->client_id,
            'country_code' => 'EP',
            'application_no' => 'EP22000111.2',
        ]);

        $this->seedRegister([
            'EP21789012.3' => ['publication_no' => 'EP4123456'],
            'EP22000111.2' => ['application_date' => $clean->application_date->toDateString()],
        ]);

        $stats = app(RegisterReconciliation::class)->run();

        $this->assertSame(['checked' => 2, 'drift' => 1], $stats);

        $check = RegisterCheck::firstWhere('matter_id', $matter->id);
        $this->assertSame('drift', $check->status);
        $this->assertNull($check->resolved_at);
        $this->assertSame(
            [['field' => 'publication_no', 'ours' => null, 'theirs' => 'EP4123456']],
            $check->differences
        );
        $this->assertSame('ok', RegisterCheck::firstWhere('matter_id', $clean->id)->status);

        // Accepting the office values updates the matter and resolves the check
        $this->actingAs($this->user)
            ->post(route('register-checks.accept', $check))
            ->assertSessionHas('success');
        $this->assertSame('EP4123456', $matter->fresh()->publication_no);
        $this->assertNotNull($check->fresh()->resolved_at);
    }

    public function test_matters_missing_from_the_register_are_flagged(): void
    {
        $matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'country_code' => 'GB',
            'application_no' => 'GB9900001.1',
        ]);

        app(RegisterReconciliation::class)->run();

        $this->assertSame('not_found', RegisterCheck::firstWhere('matter_id', $matter->id)->status);
    }

    public function test_api_offices_look_the_register_up_live(): void
    {
        config()->set('integrations.offices.epo', [
            'name' => 'European Patent Office', 'driver' => 'api',
            'base_url' => 'https://exchange.epo.example/v1', 'token' => 't',
        ]);
        MockClient::global([
            LookupRegisterRequest::class => MockResponse::make(['record' => [
                'title' => 'Live register title', 'application_no' => 'EP24555001.1',
            ]]),
        ]);

        $this->actingAs($this->user)->post(route('matters.import'), [
            'office' => 'epo', 'application_no' => 'EP24555001.1',
            'client_id' => Client::factory()->create()->id,
        ]);

        $this->assertSame('Live register title', Matter::latest('id')->first()->title);

        MockClient::destroyGlobal();
    }
}
