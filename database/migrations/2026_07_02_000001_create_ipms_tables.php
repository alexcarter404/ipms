<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20)->default('company'); // company|individual
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Inventors, applicants, agents, associates, opponents, licensees...
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('individual'); // individual|organisation
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Patent/trademark families grouping related matters across jurisdictions
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // internal docket number
            $table->string('matter_type', 20); // patent|trademark|design|copyright|domain|other
            $table->string('title');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('matters')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('country_code', 2); // jurisdiction
            $table->string('filing_route', 20)->nullable(); // national|pct|ep|madrid|hague|paris
            $table->string('status', 30)->default('draft');
            $table->string('application_no', 50)->nullable();
            $table->date('application_date')->nullable();
            $table->string('publication_no', 50)->nullable();
            $table->date('publication_date')->nullable();
            $table->string('registration_no', 50)->nullable(); // grant / registration number
            $table->date('registration_date')->nullable();
            $table->string('priority_no', 50)->nullable();
            $table->date('priority_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['matter_type', 'status']);
            $table->index('country_code');
        });

        Schema::create('matter_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // applicant|inventor|owner|agent|associate|licensee|opponent
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['matter_id', 'party_id', 'role']);
        });

        // Nice classes for trademarks (also usable for Locarno classes on designs)
        Schema::create('matter_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('class_number');
            $table->text('specification')->nullable();
            $table->timestamps();
            $table->unique(['matter_id', 'class_number']);
        });

        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle'); // annuity year / renewal term number
            $table->date('due_date');
            $table->date('grace_date')->nullable();
            $table->string('status', 20)->default('upcoming'); // upcoming|reminder_sent|instructed|paid|lapsed|waived
            $table->decimal('official_fee', 10, 2)->nullable();
            $table->decimal('service_fee', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamp('instructed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['matter_id', 'cycle']);
            $table->index(['status', 'due_date']);
        });

        // Workflow templates: chains of deadline-driven steps triggered by an event
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('matter_type', 20)->nullable(); // null = any type
            $table->string('trigger_event', 30); // filing|publication|grant|registration|office_action|priority|manual
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('offset_value')->default(0); // relative to trigger date
            $table->string('offset_unit', 10)->default('days'); // days|weeks|months|years
            $table->boolean('is_critical')->default(false); // statutory / non-extendable deadline
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('matter_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->date('internal_date')->nullable(); // soft internal deadline before the official one
            $table->boolean('is_critical')->default(false);
            $table->string('priority', 10)->default('normal'); // low|normal|high|critical
            $table->string('status', 20)->default('pending'); // pending|in_progress|completed|cancelled
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('comm_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 10)->default('email'); // email|letter
            $table->string('matter_type', 20)->nullable(); // null = any type
            $table->string('subject')->nullable();
            $table->text('body'); // supports {{placeholder}} merge fields
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comm_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 10)->default('email');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 10)->default('draft'); // draft|sent
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
        Schema::dropIfExists('comm_templates');
        Schema::dropIfExists('matter_tasks');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('renewals');
        Schema::dropIfExists('matter_classes');
        Schema::dropIfExists('matter_party');
        Schema::dropIfExists('matters');
        Schema::dropIfExists('families');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('clients');
    }
};
