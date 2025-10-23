<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_settings','fx_manual_rate')) {
                $table->decimal('fx_manual_rate', 12, 6)->nullable()->after('fx_source');
            }
        });
    }
    public function down(): void {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn('fx_manual_rate');
        });
    }
};
