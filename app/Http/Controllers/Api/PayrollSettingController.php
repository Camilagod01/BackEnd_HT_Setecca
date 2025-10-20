<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\UpdatePayrollSettingRequest;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;

class PayrollSettingController extends Controller
{
    // GET /api/payroll-settings  → devuelve el único registro (crea con defaults si no existe)
    public function show()
    {
        $row = PayrollSetting::singleton();
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
