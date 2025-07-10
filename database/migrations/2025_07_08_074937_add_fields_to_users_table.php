<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->enum('role', ['admin', 'manager', 'courier', 'bank'])->default('courier');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('bank_id');
            $table->dropColumn(['phone', 'role', 'bank_id', 'is_active', 'note']);
        });
    }
};
