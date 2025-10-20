<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('justifications')) {
            Schema::create('justifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->date('date');                            // Fecha del evento
                $table->time('from_time')->nullable();           // Si lo necesita la tabla
                $table->time('to_time')->nullable();             // Si lo necesita la tabla
                $table->enum('type', ['late','early_leave','absence','other'])->default('late');
                $table->string('reason', 255)->nullable();       // Motivo breve
                $table->text('notes')->nullable();               // Detalle
                $table->enum('status', ['pending','approved','rejected'])->default('pending');
                $table->timestamps();

                $table->index(['employee_id','date']);
                $table->index(['date','type','status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('justifications');
    }
};
