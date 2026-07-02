<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contacts are not always people: shared docketing mailboxes and
        // generic organisation inboxes are contactable too.
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('type', 20)->default('person')->after('client_id'); // person|mailbox|organisation
        });

        // Matters link to any number of contacts, each in a role
        // (main correspondence, docketing, billing, reporting...).
        Schema::create('matter_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // main|docketing|billing|reporting|other
            $table->timestamps();
            $table->unique(['matter_id', 'contact_id', 'role']);
        });

        // Backfill: the old single matter contact becomes the 'main' link.
        foreach (DB::table('matters')->whereNotNull('contact_id')->get(['id', 'contact_id']) as $matter) {
            DB::table('matter_contact')->insert([
                'matter_id' => $matter->id,
                'contact_id' => $matter->contact_id,
                'role' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('matters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (DB::table('matter_contact')->where('role', 'main')->get() as $link) {
            DB::table('matters')->where('id', $link->matter_id)->update(['contact_id' => $link->contact_id]);
        }

        Schema::dropIfExists('matter_contact');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
