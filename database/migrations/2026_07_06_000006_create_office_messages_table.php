<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_messages', function (Blueprint $table) {
            $table->id();
            $table->string('office'); // epo | ukipo | uspto | wipo | euipo
            $table->string('external_id'); // office's message id — dedupe key
            $table->string('event_type'); // OfficeEventType
            $table->string('application_no')->nullable();
            $table->string('registration_no')->nullable();
            $table->date('event_date')->nullable();
            $table->string('summary')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('needs_review'); // needs_review | matched | processed | dismissed
            $table->json('actions')->nullable(); // audit log of what automation did
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['office', 'external_id']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_messages');
    }
};
