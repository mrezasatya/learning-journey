<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/** Audit log aksi admin (US-29) — hanya baca. */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('actor')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('entity'), fn ($q, $e) => $q->where('entity', $e))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('id')->paginate(30)->withQueryString();
        $entities = AuditLog::select('entity')->distinct()->whereNotNull('entity')->orderBy('entity')->pluck('entity');

        return view('admin.audit.index', compact('logs', 'entities'));
    }
}
