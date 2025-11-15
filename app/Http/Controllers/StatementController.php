<?php

namespace App\Http\Controllers;

use App\Services\Statements\StatementService;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function __construct(
        protected StatementService $statementService
    ) {
        $this->statementService = $statementService;
    }

    public function show(Request $request, int $employeeId)
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        if (!$from || !$to) {
            return response()->json([
                'ok' => false,
                'message' => 'Parámetros from y to son requeridos',
            ], 422);
        }

        $data = $this->statementService->buildEmployeeStatement($employeeId, $from, $to);

        return response()->json([
            'ok'        => true,
            'statement' => $data,
        ]);
    }
}
