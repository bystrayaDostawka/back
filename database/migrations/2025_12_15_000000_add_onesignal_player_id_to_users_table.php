<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onesignal_player_id')->nullable()->after('bank_key_expires_at');
            $table->index('onesignal_player_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['onesignal_player_id']);
            $table->dropColumn('onesignal_player_id');
        });
    }
};

