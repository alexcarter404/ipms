<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            // The stage's data contract: matter fields that must be known
            // for a matter to be at (or beyond) this step. Used by the
            // take-on flow to open matters mid-workflow.
            $table->json('required_fields')->nullable()->after('is_critical');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('required_fields');
        });
    }
};
