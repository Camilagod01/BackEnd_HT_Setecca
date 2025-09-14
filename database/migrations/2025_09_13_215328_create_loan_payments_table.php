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
        Schema::create('loan_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
    $table->date('due_date')->index();
    $table->decimal('amount_due',12,2);
    $table->decimal('amount_paid',12,2)->default(0);
    $table->date('paid_at')->nullable();
    $table->date('payroll_cycle_date')->nullable(); // en cuál planilla se aplicó
    $table->boolean('applied')->default(false);     // si se rebajó en esa planilla
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
