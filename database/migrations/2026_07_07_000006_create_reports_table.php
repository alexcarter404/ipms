<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // matters | tasks | renewals | wip | invoices
            $table->json('filters')->nullable();
            $table->string('schedule')->nullable(); // daily | weekly
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
