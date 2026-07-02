<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legal entities within a client group. Each entity carries its
        // own registered details and billing particulars; every client
        // has exactly one default entity used when a matter doesn't
        // name one explicitly.
        Schema::create('client_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('registration_no', 50)->nullable(); // company number
            $table->string('vat_number', 50)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->text('address')->nullable(); // registered address
            $table->string('billing_contact_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->text('billing_address')->nullable(); // falls back to registered address
            $table->string('billing_reference', 100)->nullable(); // PO / reference to quote on invoices
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'is_default']);
        });

        Schema::table('matters', function (Blueprint $table) {
            // The entity that owns / is billed for this matter.
            // Null = the client's default entity.
            $table->foreignId('client_entity_id')->nullable()
                ->after('client_id')->constrained('client_entities')->nullOnDelete();
        });

        // Backfill: promote each existing client's registered details
        // into a default entity.
        foreach (DB::table('clients')->get() as $client) {
            DB::table('client_entities')->insert([
                'client_id' => $client->id,
                'name' => $client->name,
                'vat_number' => $client->vat_number ?? null,
                'country_code' => $client->country_code,
                'address' => $client->address ?? null,
                'billing_email' => $client->email,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Registered address and VAT now live on entities.
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['address', 'vat_number']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('address')->nullable();
            $table->string('vat_number', 50)->nullable();
        });

        Schema::table('matters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_entity_id');
        });

        Schema::dropIfExists('client_entities');
    }
};
