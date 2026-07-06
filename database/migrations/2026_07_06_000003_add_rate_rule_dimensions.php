<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('email'); // timekeeper grade
        });

        Schema::table('rate_cards', function (Blueprint $table) {
            $table->string('role')->nullable()->after('user_id');
            $table->string('matter_type')->nullable()->after('client_id');
            $table->foreignId('activity_code_id')->nullable()->after('matter_type')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rate_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_code_id');
            $table->dropColumn(['role', 'matter_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
