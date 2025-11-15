<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\UpdatePayrollSettingRequest;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;

class PayrollSettingController extends Controller
{
    // GET /api/payroll-settings  → devuelve el único registro (crea con defaults si no existe)
    public function show()
    {
        $row = DB::table('payroll_settings')->first();

        // Devuelve defaults si no hay registro aún
        if (!$row) {
            return response()->json([
                // pon aquí las claves que usa tu cálculo
                'overtime_threshold' => 8,
                'overtime_multiplier' => 1.5,
                'weekly_overtime_threshold' => 48,
            ]);
        }

        return response()->json($row);
    }

    // PATCH /api/payroll-settings  → actualiza campos permitidos
    public function update(UpdatePayrollSettingRequest $request)
    {
        $row = PayrollSetting::singleton();
        $row->fill($request->validated())->save();
        return response()->json($row->fresh());
    }
}
