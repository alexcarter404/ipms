<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_agreements', function (Blueprint $table) {
            $table->foreignId('matter_id')->nullable()->change();
            $table->foreignId('client_entity_id')->nullable()->unique()->after('matter_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_entity_id');
            $table->foreignId('matter_id')->nullable(false)->change();
        });
    }
};
