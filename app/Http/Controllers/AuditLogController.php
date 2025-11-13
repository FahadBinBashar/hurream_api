<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Support\AuditLogger;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all();
        $data = AuditLogger::filter([
            'user_id' => $filters['user_id'] ?? null,
            'module' => $filters['module'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ]);

        return $this->json(['data' => $data]);
    }
}
