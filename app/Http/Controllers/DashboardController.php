<?php

namespace App\Http\Controllers;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard with genuine payroll and operational KPIs.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Basic user & system stats
        $stats = [
            'total_users' => $user->hasPermission('users.view') ? User::count() : null,
            'active_users' => $user->hasPermission('users.view') ? User::where('is_active', true)->count() : null,
            'recent_logs_count' => $user->hasPermission('audit-logs.view') ? AuditLog::count() : null,
        ];

        // 2. Real Payroll KPIs from database
        $latestPeriod = PayrollPeriod::latest('start_date')->first();

        $payrollStats = [
            'active_employees' => $user->hasPermission('employees.view')
                ? Employee::where('employment_status', 'active')->count()
                : null,
            'current_period_code' => $latestPeriod?->period_code,
            'current_period_status' => $latestPeriod?->status,
            'current_period_slips_count' => $latestPeriod
                ? $latestPeriod->payslips()->count()
                : 0,
            'current_period_net_salary' => ($latestPeriod && ($user->hasPermission('payslips.view') || $user->hasPermission('payroll-periods.view')))
                ? (float) $latestPeriod->payslips()->sum('net_salary')
                : 0,
            'pending_approval_count' => $user->hasPermission('approvals.view')
                ? Payslip::where('status', PayslipStatus::PENDING_REVIEW->value)->count()
                : null,
            'pending_periods_count' => $user->hasPermission('approvals.view')
                ? PayrollPeriod::whereIn('status', [PayrollPeriodStatus::IN_REVIEW->value, PayrollPeriodStatus::CHANGES_REQUESTED->value])->count()
                : null,
            'finalized_slips_count' => $user->hasPermission('payslips.view')
                ? Payslip::where('status', PayslipStatus::FINALIZED->value)->count()
                : null,
            'deliveries_sent_count' => $user->hasPermission('deliveries.view')
                ? PayslipDelivery::where('status', 'delivered')->count()
                : null,
            'deliveries_failed_count' => $user->hasPermission('deliveries.view')
                ? PayslipDelivery::where('status', 'failed')->count()
                : null,
        ];

        $recentActivities = $user->hasPermission('audit-logs.view')
            ? AuditLog::with('user:id,name,email')
                ->latest('created_at')
                ->take(6)
                ->get()
            : [];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'payrollStats' => $payrollStats,
            'recentActivities' => $recentActivities,
            'latestPeriod' => $latestPeriod,
        ]);
    }
}
