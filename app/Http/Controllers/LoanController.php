<?php

namespace App\Http\Controllers;

use App\Http\Requests\Loan\StoreLoanRequest;
use App\Http\Requests\Loan\UpdateLoanRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\LoanSchedulerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    /** Listado de préstamos con filtros básicos */
    public function index(Request $request)
    {
        $q = Loan::query()
            ->with(['employee:id,code,first_name,last_name'])
            ->when($request->employee_id, fn($qq) => $qq->where('employee_id', $request->employee_id))
            ->when($request->status, fn($qq) => $qq->where('status', $request->status))
            ->orderByDesc('granted_at')->orderByDesc('id');

        return $q->paginate($request->integer('per_page', 20));
    }

    /** Ver un préstamo (con cuotas) */
    public function show(Loan $loan)
    {
        return $loan->load([
            'employee:id,code,first_name,last_name',
            'payments' => fn($q) => $q->orderBy('due_date')->orderBy('id')
        ]);
    }

    /** Crear préstamo + generar cuotas con LoanSchedulerService */
    public function store(StoreLoanRequest $request, LoanSchedulerService $scheduler)
    {
        $data = $request->validated();

        $loanData = [
            'employee_id' => $data['employee_id'],
            'amount'      => $data['amount'],
            'currency'    => $data['currency'],
            'granted_at'  => $data['granted_at'],
            'status'      => $data['status'] ?? 'active',
            'notes'       => $data['notes'] ?? null,
            'created_by'  => Auth::id(),
            'updated_by'  => Auth::id(),
        ];

        $schedule = $data['schedule'] ?? ['mode' => 'next'];

        return DB::transaction(function () use ($loanData, $schedule, $scheduler) {
            $loan = Loan::create($loanData);

            // Generar plan de cuotas
            $plan = $scheduler->generatePlan([
                'amount'     => $loan->amount,
                'currency'   => $loan->currency,
                'granted_at' => $loan->granted_at->toDateString(),
            ], $schedule);

            // Insertar cuotas
            $rows = [];
            foreach ($plan as $p) {
                $rows[] = [
                    'loan_id'   => $loan->id,
                    'due_date'  => $p['due_date'],
                    'amount'    => $p['amount'],
                    'status'    => $p['status'] ?? 'pending',
                    'source'    => $p['source'] ?? 'payroll',
                    'remarks'   => $p['remarks'] ?? null,
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ];
            }
            if (!empty($rows)) {
                LoanPayment::insert($rows);
            }

            $this->audit('loans', 'create', $loan->id, [
                'loan' => $loanData,
                'schedule' => $schedule,
                'payments_created' => count($rows),
            ]);

            return response()->json($loan->load('payments'), 201);
        });
    }

    /** Actualizar datos del préstamo (no re-programa cuotas) */
    public function update(UpdateLoanRequest $request, Loan $loan)
    {
        $loan->fill($request->validated());
        $loan->updated_by = Auth::id();
        $loan->save();

        $this->audit('loans', 'update', $loan->id, $request->all());

        return $loan->load('payments');
    }

    /** Eliminar préstamo (borra cuotas por FK cascade) */
    public function destroy(Loan $loan)
    {
        $id = $loan->id;
        $loan->delete();

        $this->audit('loans', 'delete', $id, []);

        return response()->noContent();
    }

    /** Listar cuotas de un préstamo */
    public function payments(Loan $loan)
    {
    $rows = LoanPayment::where('loan_id', $loan->id)
        ->orderBy('due_date')
        ->get([
            'id',
            'loan_id',
            'due_date',
            'amount',
            'status',
            'source',
            'remarks',
            'created_at',
            'updated_at',
        ]);

    return response()->json($rows);
}

    /** Auditoría simple (best effort) */
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
            // no bloquear si no existe audit_logs
        }
    }
}
