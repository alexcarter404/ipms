<?php

namespace Database\Seeders;

use App\Enums\MatterStatus;
use App\Enums\MatterType;
use App\Enums\RenewalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TriggerEvent;
use App\Actions\Billing\AddCharge;
use App\Actions\Billing\AddDisbursement;
use App\Actions\Billing\LogTime;
use App\Actions\Billing\RaiseStageCharge;
use App\Models\ActivityCode;
use App\Models\Client;
use App\Models\CommTemplate;
use App\Models\ExchangeRate;
use App\Models\Family;
use App\Models\Matter;
use App\Models\Party;
use App\Models\RateCard;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Workflow;
use App\Actions\Integrations\CreateSubmission;
use App\Enums\SubmissionType;
use App\Models\OfficeMessage;
use App\Models\OfficeSubmission;
use App\Services\Integrations\BuildSubmissionPayload;
use App\Services\InvoiceBuilder;
use App\Services\Integrations\IngestOfficeMessages;
use App\Services\Invoicing\InvoicingProvider;
use App\Services\RenewalScheduler;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Alex Carter',
            'email' => 'admin@example.com',
            'role' => 'partner',
        ]);

        $attorney = User::factory()->create([
            'name' => 'Jordan Reeves',
            'email' => 'jordan@example.com',
            'role' => 'attorney',
        ]);

        // Auditing is off in console runs by default; the demo data
        // should carry a history, so record the rest of the seeding as
        // the admin's activity.
        config(['audit.console' => true]);
        auth()->login($admin);

        // --- Clients & contacts ---
        $acme = Client::factory()->create([
            'code' => 'ACME',
            'name' => 'Acme Industries Ltd',
            'country_code' => 'GB',
        ]);
        $acmeGb = $acme->entities()->where('is_default', true)->first();
        $acmeGb->update([
            'registration_no' => '01234567',
            'vat_number' => 'GB123456789',
            'address' => "1 Innovation Way\nCambridge CB1 2AB\nUnited Kingdom",
            'billing_contact_name' => 'Accounts Payable',
            'billing_email' => 'ap@acme.example',
            'billing_reference' => 'PO-IP-2026',
        ]);
        $acmeUs = $acme->entities()->create([
            'name' => 'Acme Industries Inc',
            'registration_no' => 'DE 556-8821',
            'country_code' => 'US',
            'address' => "2000 Liberty Plaza\nWilmington, DE 19801\nUSA",
            'billing_contact_name' => 'US Accounts Team',
            'billing_email' => 'us-invoices@acme.example',
            'billing_address' => "PO Box 4410\nWilmington, DE 19801\nUSA",
        ]);

        $acmeContact = $acme->contacts()->create([
            'name' => 'Sarah Bennett',
            'email' => 'sarah.bennett@acme.example',
            'position' => 'Head of Legal',
            'is_primary' => true,
        ]);

        $nova = Client::factory()->create([
            'code' => 'NOVA',
            'name' => 'NovaTech GmbH',
            'country_code' => 'DE',
        ]);
        $novaContact = $nova->contacts()->create([
            'name' => 'Klaus Weber',
            'email' => 'k.weber@novatech.example',
            'position' => 'IP Manager',
            'is_primary' => true,
        ]);

        $acmeDocketing = $acme->contacts()->create([
            'name' => 'Acme IP Docketing',
            'type' => 'mailbox',
            'email' => 'ip-docketing@acme.example',
        ]);

        $others = Client::factory()->count(4)->create();

        // --- Parties ---
        $inventors = Party::factory()->count(6)->create();
        $agents = Party::factory()->organisation()->count(3)->create();

        // --- Patent family: priority GB filing + EP/US members ---
        $family = Family::create([
            'reference' => 'FAM-0001',
            'name' => 'Self-sealing valve assembly',
        ]);

        $gbPriority = Matter::factory()->granted()->create([
            'reference' => 'P-2021-0001',
            'title' => 'Self-sealing valve assembly',
            'client_id' => $acme->id,
            'family_id' => $family->id,
            'responsible_user_id' => $attorney->id,
            'country_code' => 'GB',
            'application_no' => 'GB2101234.5',
            'application_date' => now()->subYears(5)->subMonths(2),
            'priority_date' => now()->subYears(5)->subMonths(2),
        ]);

        foreach ([
            ['EP', 'EP21789012.3', 'ep', null],
            ['US', '17/456,789', 'pct', $acmeUs->id],
        ] as $i => [$country, $appNo, $route, $entityId]) {
            Matter::factory()->create([
                'reference' => 'P-2021-000'.($i + 2),
                'title' => 'Self-sealing valve assembly',
                'client_id' => $acme->id,
                'client_entity_id' => $entityId,
                'family_id' => $family->id,
                'parent_id' => $gbPriority->id,
                'responsible_user_id' => $attorney->id,
                'country_code' => $country,
                'filing_route' => $route,
                'status' => MatterStatus::UnderExamination,
                'application_no' => $appNo,
                'application_date' => now()->subYears(4)->subMonths(2),
                'priority_date' => $gbPriority->priority_date,
            ]);
        }

        // --- Trademark with classes ---
        $tm = Matter::factory()->trademark()->create([
            'reference' => 'TM-2023-0001',
            'title' => 'NOVASHIELD',
            'client_id' => $nova->id,
            'responsible_user_id' => $admin->id,
            'country_code' => 'EU',
            'filing_route' => 'madrid',
            'status' => MatterStatus::Registered,
            'application_no' => '018765432',
            'application_date' => now()->subYears(3),
            'registration_no' => '018765432',
            'registration_date' => now()->subYears(2)->subMonths(6),
        ]);
        $tm->classes()->createMany([
            ['class_number' => 9, 'specification' => 'Computer software; firewalls; network security appliances.'],
            ['class_number' => 42, 'specification' => 'Design and development of computer software; IT security services.'],
        ]);

        // --- Design ---
        Matter::factory()->design()->create([
            'reference' => 'D-2024-0001',
            'title' => 'Ergonomic controller housing',
            'client_id' => $nova->id,
            'responsible_user_id' => $attorney->id,
            'country_code' => 'EU',
            'status' => MatterStatus::Registered,
            'application_date' => now()->subYears(1)->subMonths(3),
            'registration_no' => 'RCD 008765432-0001',
            'registration_date' => now()->subYears(1),
        ]);

        // --- Assorted extra matters ---
        Matter::factory()->count(6)->state(fn () => [
            'client_id' => $others->random()->id,
            'responsible_user_id' => fake()->randomElement([$admin->id, $attorney->id]),
        ])->create();

        // --- Link contacts to matters (main correspondence + docketing) ---
        foreach (Matter::where('client_id', $acme->id)->get() as $matter) {
            $matter->contacts()->attach($acmeContact->id, ['role' => 'main']);
            $matter->contacts()->attach($acmeDocketing->id, ['role' => 'docketing']);
        }
        foreach (Matter::where('client_id', $nova->id)->get() as $matter) {
            $matter->contacts()->attach($novaContact->id, ['role' => 'main']);
        }

        // --- Attach parties ---
        foreach (Matter::where('matter_type', MatterType::Patent)->get() as $matter) {
            $matter->parties()->attach(
                $matter->client_id === $acme->id ? $inventors->take(2) : $inventors->random(1),
                ['role' => 'inventor']
            );
            $matter->parties()->attach($agents->random(), ['role' => 'agent']);
        }

        // --- Renewals: seed schedule rules, generate, vary some statuses ---
        $this->call(RenewalRuleSeeder::class);
        $scheduler = app(RenewalScheduler::class);
        foreach (Matter::all() as $matter) {
            $scheduler->generate($matter);
        }
        // Make a couple of renewals due soon so the dashboard has data
        $gbPriority->renewals()->orderBy('due_date')->first()?->update([
            'due_date' => now()->addDays(21),
            'status' => RenewalStatus::ReminderSent,
        ]);
        $tm->renewals()->orderBy('due_date')->first()?->update([
            'due_date' => now()->addDays(45),
        ]);

        // --- Workflows ---
        $filingWf = Workflow::create([
            'name' => 'Patent Filing Formalities',
            'matter_type' => MatterType::Patent,
            'trigger_event' => TriggerEvent::Filing,
            'description' => 'Standard post-filing deadline chain for a new patent application.',
            'is_active' => true,
        ]);
        $filingWf->steps()->createMany([
            ['title' => 'Report filing to client', 'offset_value' => 7, 'offset_unit' => 'days', 'sort_order' => 0, 'required_fields' => ['application_no', 'application_date']],
            ['title' => 'File priority documents', 'offset_value' => 3, 'offset_unit' => 'months', 'sort_order' => 1, 'required_fields' => ['priority_no', 'priority_date']],
            ['title' => 'Request examination', 'offset_value' => 12, 'offset_unit' => 'months', 'is_critical' => true, 'sort_order' => 2, 'required_fields' => ['responsible_user_id']],
            ['title' => 'Foreign filing decision (Paris deadline)', 'offset_value' => 12, 'offset_unit' => 'months', 'is_critical' => true, 'sort_order' => 3, 'required_fields' => []],
        ]);

        $oaWf = Workflow::create([
            'name' => 'Office Action Response',
            'matter_type' => null,
            'trigger_event' => TriggerEvent::OfficeAction,
            'description' => 'Deadlines after an office action issues.',
            'is_active' => true,
        ]);
        $oaWf->steps()->createMany([
            ['title' => 'Report office action to client', 'offset_value' => 5, 'offset_unit' => 'days', 'sort_order' => 0],
            ['title' => 'Client instructions deadline', 'offset_value' => 2, 'offset_unit' => 'months', 'sort_order' => 1],
            ['title' => 'File response', 'offset_value' => 3, 'offset_unit' => 'months', 'is_critical' => true, 'sort_order' => 2],
        ]);

        $tmWf = Workflow::create([
            'name' => 'Trade Mark Registration Follow-up',
            'matter_type' => MatterType::Trademark,
            'trigger_event' => TriggerEvent::Registration,
            'description' => 'Post-registration housekeeping for trade marks.',
            'is_active' => true,
        ]);
        $tmWf->steps()->createMany([
            ['title' => 'Send registration certificate to client', 'offset_value' => 14, 'offset_unit' => 'days', 'sort_order' => 0, 'required_fields' => ['registration_no', 'registration_date']],
            ['title' => 'Diarise proof-of-use deadline', 'offset_value' => 5, 'offset_unit' => 'years', 'sort_order' => 1, 'required_fields' => []],
        ]);

        // --- Tasks: a few standalone ones, some overdue ---
        $gbPriority->tasks()->create([
            'title' => 'Consider validation states for EP grant',
            'due_date' => now()->addDays(10),
            'priority' => TaskPriority::High,
            'status' => TaskStatus::Pending,
            'assigned_to' => $attorney->id,
            'created_by' => $admin->id,
        ]);
        $tm->tasks()->create([
            'title' => 'Docket proof-of-use deadline',
            'due_date' => now()->subDays(3),
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Pending,
            'assigned_to' => $admin->id,
            'created_by' => $admin->id,
        ]);

        // --- Communication templates ---
        CommTemplate::create([
            'name' => 'Filing Confirmation',
            'channel' => 'email',
            'matter_type' => null,
            'subject' => '{{matter.reference}} — Application filed ({{matter.country}})',
            'body' => "Dear {{contact.name}},\n\nRe: {{matter.title}}\nOur ref: {{matter.reference}}\n\nWe confirm the above application was filed on {{matter.application_date}} under application number {{matter.application_no}}.\n\nWe will report further developments as they occur.\n\nKind regards,\n{{attorney.name}}\n{{attorney.email}}",
            'is_active' => true,
        ]);

        CommTemplate::create([
            'name' => 'Renewal Reminder',
            'channel' => 'email',
            'matter_type' => null,
            'subject' => '{{matter.reference}} — Renewal due {{matter.next_renewal_date}}',
            'body' => "Dear {{contact.name}},\n\nRe: {{matter.title}} ({{matter.reference}})\n\nThe next renewal for this case falls due on {{matter.next_renewal_date}}. Please let us have your instructions no later than one month before the due date.\n\nIf we do not hear from you, the right may lapse.\n\nKind regards,\n{{attorney.name}}",
            'is_active' => true,
        ]);

        CommTemplate::create([
            'name' => 'Registration Certificate Letter',
            'channel' => 'letter',
            'matter_type' => MatterType::Trademark,
            'subject' => 'Registration Certificate — {{matter.title}}',
            'body' => "{{today}}\n\n{{client.name}}\n\nDear {{contact.name}},\n\nRe: {{matter.title}} — {{matter.country}} registration no. {{matter.registration_no}}\n\nWe are pleased to enclose the certificate of registration for the above trade mark, registered on {{matter.registration_date}}.\n\nThe registration will next fall due for renewal on {{matter.next_renewal_date}}.\n\nYours sincerely,\n{{attorney.name}}",
            'is_active' => true,
        ]);

        // --- Billing: tax, FX, activity codes, rate cards ---
        $vat = TaxRate::create([
            'name' => 'UK VAT (standard)', 'rate' => 20, 'country_code' => 'GB', 'is_default' => true,
        ]);
        $zeroRated = TaxRate::create(['name' => 'Zero-rated (export)', 'rate' => 0]);

        foreach ([
            'EUR' => 1.1700, 'USD' => 1.2700, 'JPY' => 199.50, 'CNY' => 9.0500,
            'CHF' => 1.1200, 'AUD' => 1.9300, 'CAD' => 1.7400,
        ] as $code => $rate) {
            ExchangeRate::create(['currency_code' => $code, 'rate' => $rate, 'rate_date' => now()->toDateString()]);
        }

        $codes = collect([
            ['P100', 'Case assessment & strategy'],
            ['P200', 'Drafting & filing'],
            ['P300', 'Prosecution & office action response'],
            ['P400', 'Oppositions & appeals'],
            ['C100', 'Client communication & reporting'],
            ['C200', 'Foreign counsel liaison'],
            ['R100', 'Renewals administration'],
            ['G100', 'General case administration'],
        ])->mapWithKeys(fn ($row) => [
            $row[0] => ActivityCode::create(['code' => $row[0], 'description' => $row[1]]),
        ]);

        $acmeGb->update(['currency_code' => 'GBP', 'tax_rate_id' => $vat->id]);
        $acmeUs->update(['currency_code' => 'USD', 'tax_rate_id' => $zeroRated->id]);
        $nova->entities()->where('is_default', true)->first()
            ->update(['currency_code' => 'EUR', 'tax_rate_id' => $zeroRated->id]);

        RateCard::create(['currency_code' => 'GBP', 'hourly_rate' => 250, 'effective_from' => now()->subYears(2)]);
        // Grade-based rules: joiners inherit their grade's rate…
        RateCard::create(['role' => 'attorney', 'currency_code' => 'GBP', 'hourly_rate' => 240, 'effective_from' => now()->subYears(2)]);
        RateCard::create(['role' => 'case_manager', 'currency_code' => 'GBP', 'hourly_rate' => 150, 'effective_from' => now()->subYears(2)]);
        RateCard::create(['role' => 'paralegal', 'currency_code' => 'GBP', 'hourly_rate' => 120, 'effective_from' => now()->subYears(2)]);
        // …while personal rules take precedence over the grade
        RateCard::create(['user_id' => $admin->id, 'currency_code' => 'GBP', 'hourly_rate' => 320, 'effective_from' => now()->subYears(2)]);
        RateCard::create(['user_id' => $attorney->id, 'currency_code' => 'GBP', 'hourly_rate' => 260, 'effective_from' => now()->subYears(2)]);

        // --- Billing agreements across the fee-arrangement spectrum ---
        // Entity-level default: everything billed to Acme's US arm is
        // blended-hourly unless a matter overrides it.
        $acmeUs->billingAgreement()->create(['type' => 'blended', 'blended_rate' => 300]);

        $gbPriority->billingAgreement()->create([
            'type' => 'hourly', 'increment_minutes' => 6,
            'default_markup_pct' => 10, 'requires_task_codes' => true,
        ]);
        Matter::firstWhere('reference', 'P-2021-0002')->billingAgreement()->create([
            'type' => 'capped', 'increment_minutes' => 6, 'cap_amount' => 8000,
        ]);
        $tm->billingAgreement()->create(['type' => 'fixed', 'fixed_amount' => 3500]);
        $designAgreement = Matter::firstWhere('reference', 'D-2024-0001')
            ->billingAgreement()->create(['type' => 'stage']);
        $designAgreement->stages()->createMany([
            ['description' => 'Design search & clearance', 'amount' => 1200, 'sort_order' => 0],
            ['description' => 'Preparation & filing', 'amount' => 1800, 'sort_order' => 1],
            ['description' => 'Registration formalities', 'amount' => 900, 'sort_order' => 2],
        ]);
        app(RaiseStageCharge::class)->handle($designAgreement->stages()->first());

        // --- WIP: time, a marked-up disbursement, a fixed fee ---
        $logTime = app(LogTime::class);
        $logTime->handle($gbPriority, [
            'user_id' => $admin->id, 'work_date' => now()->subDays(9)->toDateString(),
            'minutes' => 95, 'activity_code_id' => $codes['P300']->id,
            'narrative' => 'Review examination report; prepare response strategy',
        ]);
        $logTime->handle($gbPriority, [
            'user_id' => $attorney->id, 'work_date' => now()->subDays(4)->toDateString(),
            'minutes' => 30, 'activity_code_id' => $codes['C100']->id,
            'narrative' => 'Report filing receipt to client with next steps',
        ]);
        app(AddDisbursement::class)->handle($gbPriority, [
            'date' => now()->subDays(7)->toDateString(),
            'description' => 'EPO examination fee', 'supplier' => 'EPO',
            'cost_amount' => 620, 'cost_currency' => 'EUR',
        ]);

        // --- An issued invoice with a part-payment (trade mark, EUR) ---
        app(AddCharge::class)->handle($tm, [
            'type' => 'fixed_fee', 'date' => now()->subDays(20)->toDateString(),
            'description' => 'Fixed fee — trade mark registration (agreed scope)', 'amount' => 3500,
        ]);
        app(AddDisbursement::class)->handle($tm, [
            'date' => now()->subDays(18)->toDateString(),
            'description' => 'EUIPO registration certificate fee', 'supplier' => 'EUIPO',
            'cost_amount' => 120, 'cost_currency' => 'EUR',
        ]);
        // --- Budgets: accumulate per matter, audit-stamped ---
        $gbPriority->budgets()->createMany([
            ['created_by' => $admin->id, 'description' => 'Initial prosecution budget',
             'amount' => 1000, 'currency_code' => 'GBP', 'base_amount' => 1000],
            ['created_by' => $attorney->id, 'description' => 'Uplift for examination response',
             'amount' => 500, 'currency_code' => 'GBP', 'base_amount' => 500],
        ]);
        $tm->budgets()->create([
            'created_by' => $admin->id, 'description' => 'Registration programme',
            'amount' => 4000, 'currency_code' => 'EUR', 'base_amount' => round(4000 / 1.17, 2),
        ]);
        Matter::firstWhere('reference', 'D-2024-0001')->budgets()->create([
            'created_by' => $attorney->id, 'description' => 'Design registration (fixed scope)',
            'amount' => 1000, 'currency_code' => 'EUR', 'base_amount' => round(1000 / 1.17, 2),
        ]);

        // Admin's own portfolio WIP (TM matter) for the dashboard tile
        $logTime->handle($tm, [
            'user_id' => $admin->id, 'work_date' => now()->subDays(3)->toDateString(),
            'minutes' => 45, 'activity_code_id' => $codes['C100']->id,
            'narrative' => 'Advise on renewal and watch strategy',
        ]);

        $invoice = app(InvoiceBuilder::class)->draft($tm);
        app(InvoicingProvider::class)->issue($invoice);
        app(InvoicingProvider::class)->recordPayment($invoice, [
            'date' => now()->subDays(2)->toDateString(),
            'amount' => 2000, 'method' => 'bank_transfer', 'reference' => 'SEPA 8842190',
        ]);

        // --- IP office exchange ---
        CommTemplate::create([
            'name' => 'Office Action Report',
            'channel' => 'email',
            'matter_type' => null,
            'subject' => '{{matter.reference}} — Official communication received',
            'body' => "Dear {{contact.name}},\n\nRe: {{matter.title}} ({{matter.reference}})\n\nWe write to report an official communication issued by the office. We are reviewing it and will follow with our recommendations and the response deadline shortly.\n\nKind regards,\n{{attorney.name}}",
            'is_active' => true,
            'auto_event' => 'office_action',
        ]);

        // A grant reported by the office completes the examination step.
        $filingWf->steps()->where('title', 'Request examination')
            ->update(['completed_by_event' => 'grant']);

        // Ingested + auto-processed: a USPTO office action on P-2021-0003
        // (matched by application number; applies the OA response
        // workflow and drafts the report to the client).
        app(IngestOfficeMessages::class)->ingest('uspto', [[
            'external_id' => 'USPTO-2026-88121',
            'event_type' => 'office_action',
            'application_no' => '17/456,789',
            'event_date' => now()->subDays(6)->toDateString(),
            'summary' => 'Non-final Office Action issued in respect of claims 1–14.',
            'payload' => [
                // The office action document rides along and is auto-filed
                'documents' => [[
                    'name' => 'CTNF-17456789.pdf',
                    'title' => 'Non-final Office Action (CTNF)',
                    'category' => 'office_action',
                    'mime' => 'application/pdf',
                    'content_base64' => base64_encode(
                        "%PDF-1.4 demo\nNon-final Office Action — application 17/456,789\nClaims 1-14 rejected under 35 U.S.C. 102."
                    ),
                ]],
            ],
        ]]);

        // Matched but waiting for a human to hit Process: an EPO grant
        // with official fees in the payload.
        OfficeMessage::create([
            'office' => 'epo',
            'external_id' => 'EPO-2026-55210',
            'event_type' => 'grant',
            'application_no' => 'EP21789012.3',
            'registration_no' => 'EP3456789',
            'event_date' => now()->subDays(2)->toDateString(),
            'summary' => 'Decision to grant a European patent (Art. 97(1) EPC).',
            'payload' => [
                'registration_no' => 'EP3456789',
                'registration_date' => now()->subDays(2)->toDateString(),
                'fees' => [
                    ['description' => 'Grant and publishing fee', 'amount' => 960, 'currency' => 'EUR'],
                ],
            ],
            'matter_id' => Matter::firstWhere('reference', 'P-2021-0002')->id,
            'status' => 'matched',
            'received_at' => now()->subDay(),
        ]);

        // --- Outbound submissions ---
        // Draft OA response on P-2021-0003, discharging the task the
        // inbound office action created above.
        $oaMatter = Matter::firstWhere('reference', 'P-2021-0003');
        app(CreateSubmission::class)->handle($oaMatter, $attorney, [
            'office' => 'uspto',
            'submission_type' => 'oa_response',
            'task_id' => $oaMatter->tasks()->where('title', 'File response')->first()?->id,
            'notes' => 'Response traversing the §102 rejection; claims 1 and 8 amended.',
        ]);

        // An already-acknowledged renewal payment with the office receipt.
        OfficeSubmission::create([
            'office' => 'ukipo',
            'matter_id' => $gbPriority->id,
            'submission_type' => 'renewal_payment',
            'payload' => app(BuildSubmissionPayload::class)->handle(
                $gbPriority, SubmissionType::RenewalPayment
            ),
            'status' => 'acknowledged',
            'external_ref' => 'UKIPO-RCPT-4471',
            'receipt' => ['receipt_id' => 'UKIPO-RCPT-4471', 'received' => now()->subDays(10)->toDateString()],
            'created_by' => $attorney->id,
            'submitted_at' => now()->subDays(10),
            'acknowledged_at' => now()->subDays(9),
        ]);

        // Unmatched: number not on the docket — needs review.
        OfficeMessage::create([
            'office' => 'epo',
            'external_id' => 'EPO-2026-55388',
            'event_type' => 'publication',
            'application_no' => 'EP99123456.7',
            'event_date' => now()->subDays(3)->toDateString(),
            'summary' => 'Publication of the application under Art. 93 EPC.',
            'payload' => ['publication_no' => 'EP4499123'],
            'status' => 'needs_review',
            'received_at' => now()->subDays(2),
        ]);

        // --- Documents on the docket ---
        $store = app(\App\Actions\Documents\StoreDocument::class);
        $store->fromContent($gbPriority, 'specification-as-filed.pdf',
            "%PDF-1.4 demo\nSpecification as filed — GB2101234.5\nSelf-sealing valve assembly.", [
                'title' => 'Specification as filed',
                'category' => \App\Enums\DocumentCategory::FiledDocument,
                'source' => 'upload',
                'mime' => 'application/pdf',
                'uploaded_by' => $admin->id,
            ]);
        app(\App\Actions\Documents\GenerateDocument::class)->handle(
            $gbPriority,
            CommTemplate::firstWhere('name', 'Filing Confirmation') ?? CommTemplate::first(),
            $admin,
            'Filing confirmation letter'
        );

        // --- Mailroom: inbound email capture ---
        $mailroom = app(\App\Services\Mailroom\IngestInboundMail::class);
        // Matched by the reference in the subject; attachment auto-filed
        $mailroom->ingest([
            'message_id' => 'MSG-2026-0611-01',
            'from' => 'r.mendez@epo-associates.example',
            'from_name' => 'Ricardo Mendez',
            'subject' => 'Re: P-2021-0002 — examination report enclosed',
            'body' => "Dear colleagues,\n\nPlease find enclosed the examination report for the above case. We recommend responding within the official term.\n\nKind regards,\nRicardo",
            'received_at' => now()->subDays(1)->toDateTimeString(),
            'attachments' => [[
                'name' => 'examination-report.pdf',
                'mime' => 'application/pdf',
                'content_base64' => base64_encode("%PDF-1.4 demo\nExamination report — EP21789012.3"),
            ]],
        ]);
        // No reference or number anywhere — waits in the mailroom
        $mailroom->ingest([
            'message_id' => 'MSG-2026-0611-02',
            'from' => 'accounts@acme.example',
            'from_name' => 'Acme Accounts Payable',
            'subject' => 'Purchase order update for your invoices',
            'body' => "Hello,\n\nOur PO reference for IP work has changed to PO-IP-2027. Please quote it on future invoices.\n\nThanks,\nAP team",
            'received_at' => now()->subHours(5)->toDateTimeString(),
        ]);

        // --- A little edit history for the audit trail demo ---
        auth()->login($attorney);
        Matter::firstWhere('reference', 'P-2021-0001')->update([
            'description' => 'Self-sealing valve for hydraulic couplings. '
                .'Scope narrowed to the two-stage seal during examination.',
        ]);
        auth()->logout();
        config(['audit.console' => false]);
    }
}
