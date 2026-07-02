<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renewal schedule templates. The scheduler resolves the most
        // specific active rule for a matter: (type, country) first,
        // falling back to (type, null) = any country.
        Schema::create('renewal_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('matter_type', 20);
            $table->string('country_code', 2)->nullable(); // null = any country
            $table->string('base_date', 20)->default('application'); // application|registration
            // Regular schedule: due = base + (cycle × interval_years),
            // for cycles start_cycle..end_cycle.
            $table->unsignedInteger('start_cycle')->nullable();
            $table->unsignedInteger('end_cycle')->nullable();
            $table->unsignedInteger('interval_years')->nullable();
            // Irregular schedule (e.g. US maintenance fees at 3.5/7.5/11.5
            // years): month offsets from the base date, one per cycle.
            // When set, it takes precedence over the regular fields.
            // An empty array means the right has no renewals (e.g. US designs).
            $table->json('offsets_months')->nullable();
            $table->unsignedInteger('grace_months')->default(6);
            $table->decimal('default_official_fee', 10, 2)->nullable();
            $table->decimal('default_service_fee', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['matter_type', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_rules');
    }
};
