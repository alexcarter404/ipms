<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_role')->default('professional')->after('role');
        });

        // Ethical walls: a client with wall entries is only visible to
        // the users behind the wall (and administrators).
        Schema::create('client_walls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_walls');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('access_role'));
    }
};
