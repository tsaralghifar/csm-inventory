<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-audit-log');

        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Filter: pencarian teks
        if ($request->search) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where('description', 'ilike', "%{$q}%")
                  ->orWhere('user_name',  'ilike', "%{$q}%")
                  ->orWhere('module',     'ilike', "%{$q}%");
            });
        }

        // Filter: modul
        if ($request->module) {
            $query->where('module', $request->module);
        }

        // Filter: aksi
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // Filter: user
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter: rentang tanggal
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min((int) ($request->per_page ?? 50), 200);
        $data    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => [
                'total'     => $data->total(),
                'page'      => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page'  => $data->perPage(),
            ],
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        $this->authorize('view-audit-log');

        return response()->json([
            'success' => true,
            'data'    => $auditLog->load('user'),
        ]);
    }

    /** Kembalikan daftar modul & aksi yang tersedia (untuk dropdown filter) */
    public function meta()
    {
        $this->authorize('view-audit-log');

        $modules = AuditLog::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json([
            'success' => true,
            'data'    => [
                'modules' => $modules,
                'actions' => $actions,
            ],
        ]);
    }
}
