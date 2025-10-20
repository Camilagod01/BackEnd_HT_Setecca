<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique()->index();         // Fecha del feriado
                $table->string('name', 120);                      // Nombre (ej. “Día de la Independencia”)
                $table->enum('scope', ['national','company'])     // Ámbito del feriado
                      ->default('national')->index();
                $table->boolean('paid')->default(true);           // Si lo necesita la tabla
                $table->timestamps();
                $table->index(['scope','paid'], 'holidays_scope_paid_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
