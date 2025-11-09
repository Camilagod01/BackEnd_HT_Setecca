<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Objetivo Sprint 7 (sick_leaves):
         *  - Campos base: employee_id (FK), start_date, end_date, total_days, notes, created_by, updated_by, timestamps
         *  - provider: ENUM('CCSS','INS','OTHER') default 'CCSS'  [MySQL] | string(20) con datos normalizados [SQLite]
         *  - coverage_percent: DECIMAL(5,2) default 0.00 (sin romper SQLite)
         *  - status: ENUM('pending','approved','rejected') default 'pending' [MySQL] | string(20) con datos normalizados [SQLite]
         */

        // 1) Campos base (no tocar orden/posiciones para evitar AFTER en SQLite)
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
            if (!Schema::hasColumn('sick_leaves', 'created_at')) {
                $table->timestamps();
            }
        });

        // 2) provider / status con compatibilidad de motor
        $driver = DB::getDriverName();

        if ($driver !== 'sqlite') {
            // --- MySQL/MariaDB: usar ENUM y MODIFY/ADD según exista o no ---
            if (Schema::hasColumn('sick_leaves', 'provider')) {
                DB::statement("ALTER TABLE sick_leaves MODIFY provider ENUM('CCSS','INS','OTHER') NOT NULL DEFAULT 'CCSS'");
            } else {
                Schema::table('sick_leaves', function (Blueprint $table) {
                    $table->enum('provider', ['CCSS','INS','OTHER'])->default('CCSS');
                });
            }

            if (Schema::hasColumn('sick_leaves', 'status')) {
                DB::statement("ALTER TABLE sick_leaves MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
            } else {
                Schema::table('sick_leaves', function (Blueprint $table) {
                    $table->enum('status', ['pending','approved','rejected'])->default('pending');
                });
            }
        } else {
            // --- SQLite: no hay ENUM ni MODIFY → usamos string + normalización de datos ---
            if (!Schema::hasColumn('sick_leaves', 'provider')) {
                Schema::table('sick_leaves', function (Blueprint $table) {
                    $table->string('provider', 20)->nullable();
                });
            }
            DB::table('sick_leaves')->whereNull('provider')->update(['provider' => 'CCSS']);

            if (!Schema::hasColumn('sick_leaves', 'status')) {
                Schema::table('sick_leaves', function (Blueprint $table) {
                    $table->string('status', 20)->nullable();
                });
            }
            DB::table('sick_leaves')->whereNull('status')->update(['status' => 'pending']);
        }

        // 3) coverage_percent con compatibilidad
        if (Schema::hasColumn('sick_leaves', 'coverage_percent')) {
            if ($driver !== 'sqlite') {
                // MySQL/MariaDB: forzar tipo/DEFAULT
                DB::statement("ALTER TABLE sick_leaves MODIFY coverage_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00");
            } else {
                // SQLite: no se puede MODIFY → normalizar por datos
                DB::table('sick_leaves')->whereNull('coverage_percent')->update(['coverage_percent' => 0.00]);
                // (Si existiera como texto, igual funciona para tests; no alteramos el tipo en SQLite)
            }
        } else {
            Schema::table('sick_leaves', function (Blueprint $table) {
                $table->decimal('coverage_percent', 5, 2)->default(0.00);
            });
        }
    }

    public function down(): void
    {
        // Reversión conservadora (no borramos columnas ni datos).
        // Solo intentamos relajar tipos en MySQL si fuese necesario; en SQLite no hacemos nada.
        try {
            if (DB::getDriverName() !== 'sqlite') {
                if (Schema::hasColumn('sick_leaves', 'provider')) {
                    DB::statement("ALTER TABLE sick_leaves MODIFY provider VARCHAR(20) NOT NULL");
                }
                if (Schema::hasColumn('sick_leaves', 'status')) {
                    DB::statement("ALTER TABLE sick_leaves MODIFY status VARCHAR(20) NOT NULL");
                }
                if (Schema::hasColumn('sick_leaves', 'coverage_percent')) {
                    DB::statement("ALTER TABLE sick_leaves MODIFY coverage_percent DECIMAL(5,2) NOT NULL");
                }
            }
        } catch (\Throwable $e) {
            // Ignorar en caso de motores/estados que no soporten el cambio inverso
        }
    }
};
