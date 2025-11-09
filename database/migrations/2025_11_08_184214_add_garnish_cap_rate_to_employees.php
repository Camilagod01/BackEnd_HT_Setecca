<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'garnish_cap_rate')) {
                // porcentaje máximo permitido para embargos, ej. 0.5 = 50%
                $table->decimal('garnish_cap_rate', 5, 4)->nullable()->after('status');
            }
        });
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
