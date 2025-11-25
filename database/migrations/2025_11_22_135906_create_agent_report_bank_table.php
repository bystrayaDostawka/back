<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_report_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_report_id')->constrained('agent_reports')->onDelete('cascade');
            $table->foreignId('bank_id')->constrained('banks')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['agent_report_id', 'bank_id']);
            $table->index('bank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_report_bank');
    }
};