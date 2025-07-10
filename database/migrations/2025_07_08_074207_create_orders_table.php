<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->onDelete('cascade');
            $table->string('product');
            $table->string('client_name');
            $table->string('client_phone');
            $table->string('client_address');
            $table->dateTime('delivery_at');
            $table->dateTime('deliveried_at')->nullable();
            $table->foreignId('courier_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('order_status_id')->constrained('order_statuses')->onDelete('cascade');
            $table->text('note')->nullable();
            $table->text('declined_reason')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
