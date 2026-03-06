<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function index()
    {
        $logs = DB::table('system_logs')
            ->orderBy('created_at', 'desc')
            ->limit(1000) // Limit to recent 100 logs
            ->get();

        return response()->json($logs);
    }
}
