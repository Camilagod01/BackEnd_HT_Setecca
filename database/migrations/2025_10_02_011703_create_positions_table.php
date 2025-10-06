<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
           $table->id();
      $table->string('code', 32)->unique();     // ej. GER, TEC, ADM
      $table->string('name');                   // Gerente, Técnico, etc.
      $table->decimal('base_hourly_rate', 12, 2); // tarifa base por hora
      $table->string('currency', 3)->default('CRC'); // CRC o USD (por ahora fijo aquí)
      $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
