<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si lo necesita la tabla
        if (!Schema::hasTable('absences')) {
            Schema::create('absences', function (Blueprint $table) {
                $table->id();

                // Relaciones
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

                // Rango de fechas
                $table->date('start_date')->index();
                $table->date('end_date')->index();

                // Tipo de ausencia: día completo u horas parciales
                $table->enum('kind', ['full_day', 'hours'])->default('full_day');

                // Si lo necesita la tabla: cantidad de horas cuando kind = 'hours'
                $table->decimal('hours', 5, 2)->nullable(); // Ej: 1.50, 3.00, etc.

                // Motivo / razón corta (opcional)
                $table->string('reason', 150)->nullable();

                // Estado del permiso
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();

                // Observaciones
                $table->text('notes')->nullable();

                // Auditoría (opcional)
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                // Índice útil para búsquedas por empleado y fechas
                $table->index(['employee_id', 'start_date', 'end_date'], 'abs_idx_emp_dates');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};

