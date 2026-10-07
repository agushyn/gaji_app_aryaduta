<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user:id,name,email');

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'users' => $users,
            'filters' => $request->only(['action', 'user_id']),
        ]);
    }
}
