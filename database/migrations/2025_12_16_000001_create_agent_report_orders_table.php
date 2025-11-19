<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_report_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_report_id')->constrained('agent_reports')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['agent_report_id', 'order_id']);
            $table->index('agent_report_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_report_orders');
    }
};



