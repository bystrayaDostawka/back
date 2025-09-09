<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->onDelete('cascade');
            $table->text('product');
            $table->text('name');
            $table->text('surname');
            $table->text('patronymic');
            $table->text('phone');
            $table->text('address');
            $table->dateTime('delivery_at');
            $table->dateTime('delivered_at')->nullable();
            $table->foreignId('courier_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('order_status_id')->constrained('order_statuses')->onDelete('cascade');
            $table->text('note')->nullable();
            $table->text('declined_reason')->nullable();
            $table->timestamps();
            $table->index('bank_id');
            $table->index('courier_id');
            $table->index('order_status_id');
            $table->index('delivery_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
