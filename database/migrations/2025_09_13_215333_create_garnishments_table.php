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
      Schema::create('garnishments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->string('order_no')->nullable();
    $table->enum('mode',['percent','amount'])->default('percent');
    $table->decimal('value',8,2); // % o monto fijo
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->integer('priority')->default(1);
    $table->boolean('active')->default(true);
    $table->text('notes')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garnishments');
    }
};
