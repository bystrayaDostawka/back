<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->enum('role', ['admin', 'manager', 'courier', 'bank'])->default('courier');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('bank_id');
            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
