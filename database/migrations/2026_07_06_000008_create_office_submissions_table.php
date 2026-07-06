<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('office');
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('matter_tasks')->nullOnDelete();
            $table->string('submission_type'); // filing | oa_response | renewal_payment | document
            $table->json('payload');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft'); // draft | submitted | acknowledged | failed
            $table->string('external_ref')->nullable(); // office receipt number
            $table->json('receipt')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_submissions');
    }
};
