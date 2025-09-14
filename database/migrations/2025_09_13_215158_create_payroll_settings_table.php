<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
    $table->id();
    $table->decimal('workday_hours',5,2)->default(8);     // horas por jornada
    $table->decimal('overtime_threshold',5,2)->default(8);// umbral extras
    $table->string('base_currency',3)->default('CRC');
    $table->enum('fx_mode',['manual','automatic'])->default('manual');
    $table->string('fx_source')->nullable(); // p.ej. BCCR / ECB
    $table->string('rounding_mode')->default('half_up');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
