<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // permitir nulos por seguridad (evita 500 al crear sin salario)
            $table->decimal('default_salary_amount', 12, 2)->nullable()->change();
            $table->enum('default_salary_currency', ['CRC', 'USD'])->default('CRC')->nullable()->change();

            // nuevo campo para estado activo
            if (!Schema::hasColumn('positions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('default_salary_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
