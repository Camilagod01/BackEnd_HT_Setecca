<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Si lo necesita la tabla: asegurar columnas y tipos objetivo del Sprint 7
        // Objetivo:
        // id, employee_id (FK, cascade), start_date, end_date, total_days,
        // provider ENUM('CCSS','INS','OTHER') default 'CCSS',
        // coverage_percent DECIMAL(5,2) default 0.00,
        // status ENUM('pending','approved','rejected') default 'pending',
        // notes TEXT nullable, created_by, updated_by, timestamps

        // FK y campos base
        Schema::table('sick_leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('sick_leaves', 'employee_id')) {
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('sick_leaves', 'start_date')) {
                $table->date('start_date')->index();
            }
            if (!Schema::hasColumn('sick_leaves', 'end_date')) {
                $table->date('end_date')->index();
            }
            if (!Schema::hasColumn('sick_leaves', 'total_days')) {
                $table->unsignedSmallInteger('total_days');
            }
            if (!Schema::hasColumn('sick_leaves', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('sick_leaves', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (!Schema::hasColumn('sick_leaves', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
        });

        // provider enum
        if (Schema::hasColumn('sick_leaves', 'provider')) {
            DB::statement("ALTER TABLE sick_leaves MODIFY provider ENUM('CCSS','INS','OTHER') NOT NULL DEFAULT 'CCSS'");
        } else {
            Schema::table('sick_leaves', function (Blueprint $table) {
                $table->enum('provider', ['CCSS','INS','OTHER'])->default('CCSS')->after('total_days');
            });
        }

        // coverage_percent
        if (Schema::hasColumn('sick_leaves', 'coverage_percent')) {
            DB::statement("ALTER TABLE sick_leaves MODIFY coverage_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00");
        } else {
            Schema::table('sick_leaves', function (Blueprint $table) {
                $table->decimal('coverage_percent', 5, 2)->default(0.00)->after('provider');
            });
        }

        // status enum (volver a crear si difiere)
        if (Schema::hasColumn('sick_leaves', 'status')) {
            DB::statement("ALTER TABLE sick_leaves MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('sick_leaves', function (Blueprint $table) {
                $table->enum('status', ['pending','approved','rejected'])->default('pending')->after('coverage_percent');
            });
        }
    }

    public function down(): void
    {
        // Reversión conservadora: no eliminamos columnas, solo intentamos revertir tipos críticos a algo genérico
        try {
            if (Schema::hasColumn('sick_leaves', 'provider')) {
                DB::statement("ALTER TABLE sick_leaves MODIFY provider VARCHAR(20) NOT NULL");
            }
            if (Schema::hasColumn('sick_leaves', 'coverage_percent')) {
                DB::statement("ALTER TABLE sick_leaves MODIFY coverage_percent DECIMAL(5,2) NOT NULL");
            }
            if (Schema::hasColumn('sick_leaves', 'status')) {
                DB::statement("ALTER TABLE sick_leaves MODIFY status VARCHAR(20) NOT NULL");
            }
        } catch (\Throwable $e) {
            // Si lo necesita la tabla: ignorar errores de down para no bloquear rollbacks
        }
    }
};
