<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sick_leaves', function (Blueprint $table) {
            if (Schema::hasColumn('sick_leaves', 'status')) {
                $table->dropColumn('status');
            }

            $table->enum('type', ['50pct','0pct'])->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sick_leaves', function (Blueprint $table) {
            $table->string('status')->default('approved');
            $table->string('type')->nullable()->change();
        });
    }
};
