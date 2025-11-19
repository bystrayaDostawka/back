<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('agent_reports', 'bank_id')) {
            // перенести существующие значения bank_id в pivot
            $reports = DB::table('agent_reports')->select('id', 'bank_id')->whereNotNull('bank_id')->get();
            foreach ($reports as $report) {
                DB::table('agent_report_bank')->updateOrInsert(
                    [
                        'agent_report_id' => $report->id,
                        'bank_id' => $report->bank_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        Schema::table('agent_reports', function (Blueprint $table) {
            if (Schema::hasColumn('agent_reports', 'bank_id')) {
                $table->dropForeign(['bank_id']);
                $table->dropColumn('bank_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_reports', 'bank_id')) {
                $table->foreignId('bank_id')->nullable()->constrained('banks')->cascadeOnDelete();
                $table->index('bank_id');
            }
        });
    }
};


