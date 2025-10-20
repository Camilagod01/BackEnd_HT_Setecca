<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_settings')) {
            Schema::create('payroll_settings', function (Blueprint $table) {
                $table->id();

                // Clave única del parámetro (ej: 'insurance_rate', 'pension_rate', 'max_advance_pct')
                $table->string('key', 120)->unique();

                // Valor en JSON para parámetros compuestos
                $table->json('value')->nullable();

                // Campos auxiliares para valores simples
                $table->decimal('value_decimal', 12, 4)->nullable();
                $table->string('value_string', 255)->nullable();
                $table->boolean('value_bool')->default(false);

                // Agrupación y descripción
                $table->string('group', 60)->default('general')->index(); // general|deductions|incapacity|advances|loans|overtime
                $table->string('description', 255)->nullable();

                // Vigencia opcional
                $table->date('effective_from')->nullable()->index();

                // Auditoría
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                $table->index(['key', 'effective_from'], 'payroll_settings_key_eff_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
