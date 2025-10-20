
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'use_position_salary')) {
                $table->boolean('use_position_salary')->default(true);
            }
            if (!Schema::hasColumn('employees', 'salary_type')) {
                $table->enum('salary_type', ['monthly','hourly'])->default('monthly');
            }
            if (!Schema::hasColumn('employees', 'salary_override_amount')) {
                $table->decimal('salary_override_amount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('employees', 'salary_override_currency')) {
                $table->enum('salary_override_currency', ['CRC','USD'])->nullable();
            }
        });
    }

    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'salary_override_currency')) $table->dropColumn('salary_override_currency');
            if (Schema::hasColumn('employees', 'salary_override_amount')) $table->dropColumn('salary_override_amount');
            if (Schema::hasColumn('employees', 'salary_type')) $table->dropColumn('salary_type');
            if (Schema::hasColumn('employees', 'use_position_salary')) $table->dropColumn('use_position_salary');
        });
    }
};
