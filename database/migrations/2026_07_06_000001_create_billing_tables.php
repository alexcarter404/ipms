<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2); // percent
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency_code', 3);
            $table->decimal('rate', 14, 6); // 1 base unit = rate × currency
            $table->date('rate_date');
            $table->timestamps();
            $table->unique(['currency_code', 'rate_date']);
        });

        Schema::create('activity_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('billing_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type'); // hourly | blended | capped | fixed | stage
            $table->char('currency_code', 3)->nullable(); // null → billing entity's currency
            $table->unsignedSmallInteger('increment_minutes')->default(6);
            $table->decimal('blended_rate', 10, 2)->nullable();
            $table->decimal('cap_amount', 12, 2)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->decimal('default_markup_pct', 5, 2)->default(0);
            $table->boolean('requires_task_codes')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_agreement_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->decimal('hourly_rate', 10, 2);
            $table->date('effective_from');
            $table->timestamps();
            $table->index(['user_id', 'client_id', 'effective_from']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->nullable()->unique(); // assigned at issue
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency_code', 3);
            $table->string('status')->default('draft'); // draft | issued | paid | void
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->string('tax_name')->nullable();
            $table->decimal('tax_pct', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('billable');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_code_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->unsignedInteger('billed_minutes');
            $table->decimal('rate', 10, 2);
            $table->char('currency_code', 3);
            $table->decimal('amount', 12, 2);
            $table->text('narrative')->nullable();
            $table->string('status')->default('billable'); // billable | billed | written_off | non_billable
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description');
            $table->string('supplier')->nullable();
            $table->decimal('cost_amount', 12, 2);
            $table->char('cost_currency', 3);
            $table->decimal('markup_pct', 5, 2)->default(0);
            $table->decimal('amount', 12, 2); // billed amount, in billing currency
            $table->char('currency_code', 3);
            $table->string('status')->default('billable');
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('billing_agreement_stages')->nullOnDelete();
            $table->string('type'); // fixed_fee | stage_payment | other
            $table->date('date');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->char('currency_code', 3);
            $table->string('status')->default('billable');
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_no')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency_code', 3);
            $table->string('status')->default('draft'); // draft | sent | accepted | declined
            $table->date('valid_until')->nullable();
            $table->string('tax_name')->nullable();
            $table->decimal('tax_pct', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('client_entities', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('GBP')->after('billing_reference');
            $table->foreignId('tax_rate_id')->nullable()->after('currency_code')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('currency_code');
        });

        foreach ([
            'quote_lines', 'quotes', 'payments', 'charges', 'disbursements',
            'time_entries', 'invoice_lines', 'invoices', 'rate_cards',
            'billing_agreement_stages', 'billing_agreements', 'activity_codes',
            'exchange_rates', 'tax_rates',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
