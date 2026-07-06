<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->string('direction')->default('outbound')->after('channel');
            $table->string('from_name')->nullable()->after('direction');
            $table->string('from_email')->nullable()->after('from_name');
            $table->timestamp('received_at')->nullable()->after('sent_at');
            // The mailbox message id — ingestion is idempotent on it
            $table->string('external_id')->nullable()->after('received_at');
            // Attachments held as payload until the email is matched to a
            // matter, then filed as documents and replaced by references
            $table->json('attachments')->nullable()->after('external_id');
        });

        // Unmatched inbound mail waits in the mailroom without a matter
        Schema::table('communications', function (Blueprint $table) {
            $table->foreignId('matter_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn(['direction', 'from_name', 'from_email', 'received_at', 'external_id', 'attachments']);
        });
    }
};
