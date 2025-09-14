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
        Schema::create('loans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->decimal('principal',12,2);
    $table->decimal('interest_rate',5,2)->default(0); // % anual simple
    $table->date('start_date');
    $table->integer('term_months')->default(12);
    $table->enum('payment_frequency',['weekly','biweekly','monthly'])->default('biweekly');
    $table->string('status')->default('active'); // active|paid|defaulted|canceled
    $table->text('notes')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
