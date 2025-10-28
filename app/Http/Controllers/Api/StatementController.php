<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatementService;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function show($id, Request $req, StatementService $svc)
    {
        $from = $req->query('from', now()->startOfMonth()->toDateString());
        $to   = $req->query('to',   now()->endOfMonth()->toDateString());
        return response()->json($svc->generate((int)$id, $from, $to));
    }
}
