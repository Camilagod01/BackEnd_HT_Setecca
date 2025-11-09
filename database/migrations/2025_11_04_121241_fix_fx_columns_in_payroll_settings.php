<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // Ajustar enum o convertir a varchar seguro
            $table->string('fx_mode', 20)->default('manual')->change();
            $table->string('fx_source', 20)->default('manual')->change();
            $table->string('rounding_mode', 20)->default('none')->change();
            $table->decimal('fx_manual_rate', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // Si querés revertir (no obligatorio)
            $table->enum('fx_mode', ['manual'])->default('manual')->change();
            $table->enum('fx_source', ['manual'])->default('manual')->change();
        });
    }
};

