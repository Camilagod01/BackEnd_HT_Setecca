<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Http\Requests\LoanPayment\UpdateLoanPaymentRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanPaymentController extends Controller
{
    /**
     * Actualiza una cuota.
     * Soporta:
     *  - action=mark_paid        → status=paid
     *  - action=mark_skipped     → status=skipped
     *  - action=reschedule       → due_date (y opcional amount/source/remarks)
     *  - o actualización directa de campos (status, due_date, amount, source, remarks)
     */
    public function update(UpdateLoanPaymentRequest $request, LoanPayment $loanPayment)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($loanPayment, $data) {
            // Atajos por 'action'
            if (!empty($data['action'])) {
                switch ($data['action']) {
                    case 'mark_paid':
                        $loanPayment->status = 'paid';
                        break;
                    case 'mark_skipped':
                        $loanPayment->status = 'skipped';
                        break;
                    case 'reschedule':
                        if (empty($data['due_date'])) {
                            return response()->json(['message' => 'Se requiere due_date para reprogramar.'], 422);
                        }
                        $loanPayment->due_date = $data['due_date'];
                        // se permite opcionalmente ajustar amount/source/remarks
                        if (isset($data['amount']))  $loanPayment->amount = $data['amount'];
                        if (isset($data['source']))  $loanPayment->source = $data['source'];
                        if (array_key_exists('remarks', $data)) $loanPayment->remarks = $data['remarks'];
                        break;
                    default:
                        return response()->json(['message' => 'Acción no soportada.'], 422);
                }
            } else {
                // Actualización directa de campos permitidos
                if (isset($data['status']))   $loanPayment->status = $data['status'];
                if (isset($data['due_date'])) $loanPayment->due_date = $data['due_date'];
                if (isset($data['amount']))   $loanPayment->amount = $data['amount'];
                if (isset($data['source']))   $loanPayment->source = $data['source'];
                if (array_key_exists('remarks', $data)) $loanPayment->remarks = $data['remarks'];
            }

            $loanPayment->save();

            // Si todas las cuotas quedan pagadas → cerrar el préstamo
            $this->closeLoanIfAllPaid($loanPayment->loan);

            // Auditoría (best effort)
            $this->audit('loan_payments', 'update', $loanPayment->id, $data);

            return $loanPayment->fresh();
        });
    }

    /** Cierra el préstamo si todas sus cuotas están 'paid' */
    private function closeLoanIfAllPaid(Loan $loan): void
    {
        $total = $loan->payments()->count();
        if ($total === 0) return;

        $paid = $loan->payments()->where('status', 'paid')->count();

        if ($paid === $total && $loan->status !== 'closed') {
            $loan->status = 'closed';
            $loan->updated_by = Auth::id();
            $loan->save();

            $this->audit('loans', 'auto_close', $loan->id, ['reason' => 'all_payments_paid']);
        }
    }

    /** Auditoría simple */
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
