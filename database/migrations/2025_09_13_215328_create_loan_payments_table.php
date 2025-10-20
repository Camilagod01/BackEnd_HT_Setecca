<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();

            $table->date('due_date');
            $table->decimal('amount', 12, 2);

            $table->enum('status', ['pending', 'paid', 'skipped'])->default('pending');
            $table->enum('source', ['payroll', 'manual'])->default('payroll');

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};

