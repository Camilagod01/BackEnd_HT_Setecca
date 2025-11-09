<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'garnish_cap_rate')) {
                // 0..1 (porcentaje como fracción), default 0.50
                $table->decimal('garnish_cap_rate', 5, 4)->nullable()->after('status');
            }
        });

        // Asigna 0.50 a los nulls (y de paso a todos si viene nueva)
        DB::table('employees')
            ->whereNull('garnish_cap_rate')
            ->update(['garnish_cap_rate' => 0.50]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'garnish_cap_rate')) {
                $table->dropColumn('garnish_cap_rate');
            }
        });
    }
};
