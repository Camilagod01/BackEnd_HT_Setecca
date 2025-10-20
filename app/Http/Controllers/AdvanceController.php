<?php

namespace App\Http\Controllers;

use App\Http\Requests\Advance\StoreAdvanceRequest;
use App\Http\Requests\Advance\UpdateAdvanceRequest;
use App\Models\Advance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdvanceController extends Controller
{
    public function index(Request $request)
    {
        $q = Advance::query()
            ->with(['employee:id,code,first_name,last_name']) // ajusta columnas a tu Employee
            ->when($request->employee_id, fn($qq) => $qq->where('employee_id', $request->employee_id))
            ->when($request->status, fn($qq) => $qq->where('status', $request->status))
            ->orderByDesc('granted_at')->orderByDesc('id');

        // si usas paginación en el front, devuelve paginate; si no, usa get()
        return $q->paginate($request->integer('per_page', 20));
    }

    public function store(StoreAdvanceRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        // Regla opcional: evitar más de un "pending" simultáneo por empleado
        if (($data['status'] ?? 'pending') === 'pending') {
            $exists = Advance::where('employee_id', $data['employee_id'])
                ->where('status', 'pending')
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un adelanto pendiente para este empleado.'
                ], 422);
            }
        }

        $advance = Advance::create($data);
        $this->audit('advances', 'create', $advance->id, $data);

        return response()->json($advance->load('employee'), 201);
    }

    public function update(UpdateAdvanceRequest $request, Advance $advance)
    {
        $advance->fill($request->validated());
        $advance->updated_by = Auth::id();
        $advance->save();

        $this->audit('advances', 'update', $advance->id, $request->all());
        return $advance->load('employee');
    }

    public function destroy(Advance $advance)
    {
        $id = $advance->id;
        $advance->delete();
        $this->audit('advances', 'delete', $id, []);
        return response()->noContent();
    }

    /** Auditoría simple, no depende de policies. Si no existe audit_logs, ignora. */
    private function audit(string $table, string $action, int $rowId, array $payload): void
    {
        try {
            DB::table('audit_logs')->insert([
                'performed_by' => Auth::id(),
                'table_name'   => $table,
                'action'       => $action,
                'row_id'       => $rowId,
                'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // si no existe audit_logs o falla, no bloquea el flujo
        }
    }
}
