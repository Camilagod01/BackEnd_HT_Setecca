<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('positions', function (Blueprint $table) {
            // Nuevo modelo salarial
            if (!Schema::hasColumn('positions', 'salary_type')) {
                $table->enum('salary_type', ['monthly','hourly'])->default('monthly');
            }
            if (!Schema::hasColumn('positions', 'default_salary_amount')) {
                $table->decimal('default_salary_amount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('positions', 'default_salary_currency')) {
                $table->enum('default_salary_currency', ['CRC','USD'])->default('CRC');
            }
        });
    }

    public function down(): void {
        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'default_salary_currency')) $table->dropColumn('default_salary_currency');
            if (Schema::hasColumn('positions', 'default_salary_amount')) $table->dropColumn('default_salary_amount');
            if (Schema::hasColumn('positions', 'salary_type')) $table->dropColumn('salary_type');
        });
    }
};
