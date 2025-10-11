<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class PositionController extends Controller
{
    // GET /api/positions?search=&active=&per_page=
    public function index(Request $request)
    {
        try {
            $q = Position::query();

            if (\Schema::hasColumn('positions', 'is_active') && $request->filled('active')) {
                $q->where('is_active', (bool) $request->boolean('active'));
            }

            if ($s = $request->get('search')) {
                $q->where(function ($qq) use ($s) {
                    $qq->where('code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
                });
            }

            $perPage = (int) $request->query('per_page', 20);
            if ($perPage <= 0 || $perPage > 100) $perPage = 20;

            return response()->json(
                $q->orderBy('name')->paginate($perPage)
            );

        } catch (\Throwable $e) {
            \Log::error('positions.index_failed', [
                'err'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'q'     => $request->query(),
            ]);
            return response()->json(['message' => 'Server error fetching positions'], 500);
        }
    }

    // GET /api/positions/{id}
    public function show($id)
    {
        $pos = Position::findOrFail($id);
        return response()->json($pos);
    }

    // POST /api/positions
    public function store(Request $request)
    {
        $rules = [
            'code'  => ['required','string','max:50','unique:positions,code'],
            'name'  => ['required','string','max:150','unique:positions,name'],

            // Compatibilidad LEGACY (opcionales)
            'base_hourly_rate' => ['nullable','numeric','min:0'],
            'currency'         => ['nullable','in:CRC,USD'],

            // Campos salariales modernos
            'salary_type'              => ['required','in:monthly,hourly'],
            'default_salary_amount'    => ['nullable','numeric','min:0'],
            'default_salary_currency'  => ['required','in:CRC,USD'],

            // Si manejas bandera de activación
            'is_active' => ['sometimes','boolean'],
        ];

        $data = $request->validate($rules);

        // =======================
        // Normalización crítica
        // =======================
        // A) Si hourly -> refleja default_salary_amount en base_hourly_rate y alinea la moneda.
        if (($data['salary_type'] ?? null) === 'hourly') {
            // si no vino default_salary_amount, usar base_hourly_rate (legacy) o 0
            $amount = $data['default_salary_amount']
                ?? $data['base_hourly_rate']
                ?? 0;

            $data['default_salary_amount']   = $amount;
            $data['base_hourly_rate']        = $amount; // <- evita NOT NULL
            $data['default_salary_currency'] = $data['default_salary_currency'] ?? ($data['currency'] ?? 'CRC');
            $data['currency']                = $data['default_salary_currency']; // mantén ambas alineadas
        }
        // B) Si monthly -> base_hourly_rate debe ser 0 (tu columna es NOT NULL en la BD actual)
        if (($data['salary_type'] ?? null) === 'monthly') {
            $data['base_hourly_rate'] = $data['base_hourly_rate'] ?? 0; // <- clave para evitar 500
            // currency puede quedar con el default o lo que venga; no se usa para mensual
        }

        // Si no existe is_active en BD, quítalo para evitar error
        if (!Schema::hasColumn('positions', 'is_active')) {
            unset($data['is_active']);
        }

        $pos = Position::create($data);
        return response()->json($pos, 201);
    }

    // PATCH /api/positions/{id}
    public function update(Request $request, $id)
    {
        $pos = Position::findOrFail($id);

        $rules = [
            'code'  => ['sometimes','string','max:50', Rule::unique('positions','code')->ignore($pos->id)],
            'name'  => ['sometimes','string','max:150', Rule::unique('positions','name')->ignore($pos->id)],

            'base_hourly_rate' => ['sometimes','nullable','numeric','min:0'],
            'currency'         => ['sometimes','nullable','in:CRC,USD'],

            'salary_type'              => ['sometimes','in:monthly,hourly'],
            'default_salary_amount'    => ['sometimes','nullable','numeric','min:0'],
            'default_salary_currency'  => ['sometimes','in:CRC,USD'],

            'is_active' => ['sometimes','boolean'],
        ];

        $data = $request->validate($rules);

        // =======================
        // Normalización crítica
        // =======================
        $incomingType = $data['salary_type'] ?? $pos->salary_type;

        if ($incomingType === 'hourly') {
            // cantidad: intenta payload -> payload legacy -> valor previo
            $amount = $data['default_salary_amount']
                ?? $data['base_hourly_rate']
                ?? $pos->default_salary_amount
                ?? $pos->base_hourly_rate
                ?? 0;

            $data['default_salary_amount'] = $data['default_salary_amount'] ?? $amount;
            $data['base_hourly_rate']      = $data['base_hourly_rate']      ?? $amount;

            // moneda alineada
            $curr = $data['default_salary_currency'] ?? $pos->default_salary_currency ?? $data['currency'] ?? $pos->currency ?? 'CRC';
            $data['default_salary_currency'] = $curr;
            $data['currency']                = $data['currency'] ?? $curr;
        } else { // monthly
            // Base NOT NULL en tu BD actual ⇒ asegúrate de setear un número
            if (!array_key_exists('base_hourly_rate', $data) || is_null($data['base_hourly_rate'])) {
                $data['base_hourly_rate'] = 0; // <- evita 500 por NOT NULL
            }
        }

        if (!Schema::hasColumn('positions', 'is_active')) {
            unset($data['is_active']);
        }

        $pos->fill($data)->save();
        return response()->json($pos);
    }

    // DELETE /api/positions/{id}
    public function destroy($id)
    {
        $pos = Position::findOrFail($id);

        // Soft-off si existe is_active; si no, borrar físico
        if (Schema::hasColumn('positions', 'is_active')) {
            $pos->is_active = false;
            $pos->save();
            return response()->json(['status' => 'ok', 'message' => 'Puesto desactivado']);
        }

        $pos->delete();
        return response()->json(['status' => 'ok', 'message' => 'Puesto eliminado']);
    }
}
