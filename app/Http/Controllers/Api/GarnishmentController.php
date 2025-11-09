<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garnishment;
use Illuminate\Http\Request;
use App\Http\Requests\GarnishmentStoreRequest;
use App\Http\Requests\GarnishmentUpdateRequest;
use Illuminate\Validation\Rule;


class GarnishmentController extends Controller
{
    /**
     * GET /api/garnishments
     * Lista paginada con datos básicos del empleado
     */
   



    public function index(Request $request)
{
    $q = \App\Models\Garnishment::query()
        ->with(['employee:id,first_name,last_name']);

    // Filtros
    if ($request->filled('employee_id')) {
        $q->where('employee_id', (int)$request->integer('employee_id'));
    }

    // active: 1 / 0
    if ($request->has('active')) {
        $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (!is_null($active)) {
            $q->where('active', $active ? 1 : 0);
        }
    }

    // mode: percent|amount
    if ($request->filled('mode')) {
        $q->where('mode', $request->string('mode'));
    }

    // Búsqueda simple: en order_no o notes
    if ($request->filled('q')) {
        $term = '%' . $request->string('q') . '%';
        $q->where(function ($qq) use ($term) {
            $qq->where('order_no', 'like', $term)
               ->orWhere('notes', 'like', $term);
        });
    }

    // Orden (por defecto: id desc). Permitir sort=name, dir=asc|desc
    $sort = $request->input('sort', 'id');
    $dir  = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $allowedSorts = ['id','start_date','end_date','priority','value','created_at'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'id';
    }
    $q->orderBy($sort, $dir);

    // Paginación
    $perPage = (int) $request->input('per_page', 10);
    $perPage = max(1, min($perPage, 100));

    $page = $q->paginate($perPage);

    // Formato de salida
    return response()->json([
        'data' => $page->getCollection()->map(function ($g) {
            return [
                'id'         => $g->id,
                'employee'   => [
                    'id'         => $g->employee?->id,
                    'first_name' => $g->employee?->first_name,
                    'last_name'  => $g->employee?->last_name,
                ],
                'order_no'   => $g->order_no,
                'mode'       => $g->mode,         // percent|amount
                'value'      => (float) $g->value,
                'currency'   => 'CRC',
                'start_date' => optional($g->start_date)->toDateString(),
                'end_date'   => optional($g->end_date)->toDateString(),
                'priority'   => (int) $g->priority,
                'active'     => (bool) $g->active,
            ];
        })->values(),
        'meta' => [
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'sort'         => $sort,
            'dir'          => $dir,
        ],
    ]);
}



    public function show(int $id)
{
    $g = \App\Models\Garnishment::with(['employee:id,first_name,last_name'])
        ->findOrFail($id);

    return response()->json([
        'id'         => $g->id,
        'employee'   => [
            'id'         => $g->employee->id,
            'first_name' => $g->employee->first_name,
            'last_name'  => $g->employee->last_name,
        ],
        'order_no'   => $g->order_no,
        'mode'       => $g->mode,              // 'amount' | 'percent'
        'value'      => (float)$g->value,
        'currency'   => 'CRC',                 // fijo por ahora (tu tabla actual no tiene columna currency)
        'start_date' => $g->start_date?->toDateString(),
        'end_date'   => $g->end_date?->toDateString(),
        'priority'   => (int)$g->priority,
        'active'     => (bool)$g->active,
        'notes'      => $g->notes,
        'created_at' => $g->created_at?->toDateTimeString(),
        'updated_at' => $g->updated_at?->toDateTimeString(),
    ]);
}


public function store(Request $request)
{
    // 1) Validación alineada al schema actual
    $data = $request->validate([
        'employee_id' => ['required','integer','exists:employees,id'],
        'order_no'    => ['nullable','string','max:255'],
        'mode'        => ['required', Rule::in(['percent','amount'])],
        'value'       => ['required','numeric','min:0'],
        'start_date'  => ['required','date'],
        'end_date'    => ['nullable','date','after_or_equal:start_date'],
        'priority'    => ['nullable','integer','min:1'],
        'active'      => ['nullable','boolean'],
        'notes'       => ['nullable','string'],
    ]);

    // 2) Defaults seguros
    $data['priority'] = $data['priority'] ?? 1;
    $data['active']   = array_key_exists('active', $data) ? (bool)$data['active'] : true;

    // 2.1) Regla de negocio opcional: percent <= 100
    if ($data['mode'] === 'percent' && $data['value'] > 100) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors'  => ['value' => ['Percentage cannot exceed 100.']],
        ], 422);
    }

    // 3) Crear
    $g = Garnishment::create($data)->load(['employee:id,first_name,last_name']);

    // 4) Respuesta consistente con los GET
    return response()->json([
        'id'         => $g->id,
        'employee'   => [
            'id'         => $g->employee->id,
            'first_name' => $g->employee->first_name,
            'last_name'  => $g->employee->last_name,
        ],
        'order_no'   => $g->order_no,
        'mode'       => $g->mode,
        'value'      => (float)$g->value,
        'currency'   => 'CRC', // tu tabla no tiene currency; mantenemos CRC fijo si lo muestras en el front
        'start_date' => optional($g->start_date)->toDateString(),
        'end_date'   => optional($g->end_date)->toDateString(),
        'priority'   => (int)$g->priority,
        'active'     => (bool)$g->active,
        'notes'      => $g->notes,
        'created_at' => optional($g->created_at)->toDateTimeString(),
        'updated_at' => optional($g->updated_at)->toDateTimeString(),
    ], 201);
}



public function update(UpdateGarnishmentRequest $request, \App\Models\Garnishment $garnishment)
{
    $garnishment->update($request->validated());

    $garnishment->load('employee:id,first_name,last_name');

    return response()->json([
        'id'         => $garnishment->id,
        'employee'   => [
            'id'         => $garnishment->employee?->id,
            'first_name' => $garnishment->employee?->first_name,
            'last_name'  => $garnishment->employee?->last_name,
        ],
        'order_no'   => $garnishment->order_no,
        'mode'       => $garnishment->mode,
        'value'      => (float)$garnishment->value,
        'currency'   => 'CRC',
        'start_date' => optional($garnishment->start_date)->toDateString(),
        'end_date'   => optional($garnishment->end_date)->toDateString(),
        'priority'   => (int)$garnishment->priority,
        'active'     => (bool)$garnishment->active,
    ]);
}


public function destroy(\App\Models\Garnishment $garnishment)
{
    $garnishment->delete();

    return response()->json([
        'ok'    => true,
        'id'    => $garnishment->id,
        'msg'   => 'Garnishment deleted',
    ], 200);
}



}
