<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            // Tasks from this step auto-complete when the office reports
            // this event on the matter.
            $table->string('completed_by_event')->nullable()->after('required_fields');
        });

        Schema::table('comm_templates', function (Blueprint $table) {
            // Auto-draft this template when the office reports this event.
            $table->string('auto_event')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('completed_by_event');
        });

        Schema::table('comm_templates', function (Blueprint $table) {
            $table->dropColumn('auto_event');
        });
    }
};
