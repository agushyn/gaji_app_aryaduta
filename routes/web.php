<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\EmailSettingController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayCalendarController;
use App\Http\Controllers\PayComponentController;
use App\Http\Controllers\PayrollCalculationConfigController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SecurityPayrollController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsAppSettingController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Master Kantor (Offices Module)
    Route::get('/master/offices', [\App\Http\Controllers\OfficeController::class, 'index'])->name('master.offices.index');
    Route::post('/master/offices', [\App\Http\Controllers\OfficeController::class, 'store'])->name('master.offices.store');
    Route::put('/master/offices/{office}', [\App\Http\Controllers\OfficeController::class, 'update'])->name('master.offices.update');
    Route::delete('/master/offices/{office}', [\App\Http\Controllers\OfficeController::class, 'destroy'])->name('master.offices.destroy');
    Route::patch('/master/offices/{office}/toggle-status', [\App\Http\Controllers\OfficeController::class, 'toggleStatus'])
        ->name('master.offices.toggle-status');
    Route::get('/master/offices/{office}/divisions', [\App\Http\Controllers\OfficeController::class, 'divisions'])
        ->name('master.offices.divisions');

    // Master Departemen (Departments Module)
    Route::get('/master/departments', [DepartmentController::class, 'index'])->name('master.departments.index');
    Route::post('/master/departments', [DepartmentController::class, 'store'])->name('master.departments.store');
    Route::put('/master/departments/{department}', [DepartmentController::class, 'update'])->name('master.departments.update');
    Route::delete('/master/departments/{department}', [DepartmentController::class, 'destroy'])->name('master.departments.destroy');
    Route::patch('/master/departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])
        ->name('master.departments.toggle-status');
    Route::get('/master/departments/{department}/divisions', [DepartmentController::class, 'divisions'])
        ->name('master.departments.divisions');

    // Master Divisi (Divisions Module)
    Route::get('/master/divisions', [DivisionController::class, 'index'])->name('master.divisions.index');
    Route::post('/master/divisions', [DivisionController::class, 'store'])->name('master.divisions.store');
    Route::put('/master/divisions/{division}', [DivisionController::class, 'update'])->name('master.divisions.update');
    Route::delete('/master/divisions/{division}', [DivisionController::class, 'destroy'])->name('master.divisions.destroy');
    Route::patch('/master/divisions/{division}/toggle-status', [DivisionController::class, 'toggleStatus'])
        ->name('master.divisions.toggle-status');

    // Master Jabatan (Positions Module)
    Route::get('/master/positions', [\App\Http\Controllers\PositionController::class, 'index'])->name('master.positions.index');
    Route::post('/master/positions', [\App\Http\Controllers\PositionController::class, 'store'])->name('master.positions.store');
    Route::put('/master/positions/{position}', [\App\Http\Controllers\PositionController::class, 'update'])->name('master.positions.update');
    Route::delete('/master/positions/{position}', [\App\Http\Controllers\PositionController::class, 'destroy'])->name('master.positions.destroy');
    Route::patch('/master/positions/{position}/toggle-status', [\App\Http\Controllers\PositionController::class, 'toggleStatus'])
        ->name('master.positions.toggle-status');
    Route::get('/master/positions/active', [\App\Http\Controllers\PositionController::class, 'listActive'])
        ->name('master.positions.active');

    // Alias routes for convenience (/offices, /departments, /divisions, /positions)
    Route::get('/offices', fn () => redirect()->route('master.offices.index'))->name('offices.index');
    Route::get('/departments', fn () => redirect()->route('master.departments.index'))->name('departments.index');
    Route::get('/divisions', fn () => redirect()->route('master.divisions.index'))->name('divisions.index');
    Route::get('/positions', fn () => redirect()->route('master.positions.index'))->name('positions.index');

    // Master Karyawan (Employees Module)
    Route::resource('employees', EmployeeController::class);
    Route::patch('/employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])
        ->name('employees.toggle-status');

    // Periode Penggajian (Payroll Periods Module)
    Route::resource('payroll-periods', PayrollPeriodController::class);
    Route::post('/payroll-periods/{payrollPeriod}/generate-payroll', [PayrollPeriodController::class, 'generatePayroll'])
        ->name('payroll-periods.generate-payroll');
    Route::post('/payroll-periods/{payrollPeriod}/finalize', [PayrollPeriodController::class, 'finalize'])
        ->name('payroll-periods.finalize');

    // Data Lembur Karyawan per Periode
    Route::post('/payroll-periods/{payrollPeriod}/overtimes', [\App\Http\Controllers\OvertimeEntryController::class, 'store'])
        ->name('payroll-periods.overtimes.store');
    Route::put('/payroll-periods/{payrollPeriod}/overtimes/{overtimeEntry}', [\App\Http\Controllers\OvertimeEntryController::class, 'update'])
        ->name('payroll-periods.overtimes.update');
    Route::delete('/payroll-periods/{payrollPeriod}/overtimes/{overtimeEntry}', [\App\Http\Controllers\OvertimeEntryController::class, 'destroy'])
        ->name('payroll-periods.overtimes.destroy');
    Route::post('/payroll-periods/{payrollPeriod}/overtimes/calculate-preview', [\App\Http\Controllers\OvertimeEntryController::class, 'previewCalculation'])
        ->name('payroll-periods.overtimes.preview-calculation');

    // Master Komponen Gaji (Pay Components Module)
    Route::resource('pay-components', PayComponentController::class)->except(['create', 'show', 'edit']);
    Route::patch('/pay-components/{pay_component}/toggle-status', [PayComponentController::class, 'toggleStatus'])
        ->name('pay-components.toggle-status');

    Route::post('/payroll-configurations/overtime-settings', [PayrollCalculationConfigController::class, 'updateOvertimeSettings'])
        ->name('payroll-configurations.overtime-settings');
    Route::post('/payroll-configurations/test', [PayrollCalculationConfigController::class, 'test'])
        ->name('payroll-configurations.test');
    Route::post('/payroll-configurations/{payroll_configuration}/duplicate', [PayrollCalculationConfigController::class, 'duplicate'])
        ->name('payroll-configurations.duplicate');
    Route::patch('/payroll-configurations/{payroll_configuration}/toggle-status', [PayrollCalculationConfigController::class, 'toggleStatus'])
        ->name('payroll-configurations.toggle-status');
    Route::resource('payroll-configurations', PayrollCalculationConfigController::class);

    // Kalender Libur Indonesia & Cuti Bersama (Holiday Calendar)
    Route::post('/holiday-calendars/sync', [HolidayCalendarController::class, 'sync'])
        ->name('holiday-calendars.sync');
    Route::patch('/holiday-calendars/{holiday_calendar}/toggle-payroll-holiday', [HolidayCalendarController::class, 'togglePayrollHoliday'])
        ->name('holiday-calendars.toggle-payroll-holiday');
    Route::patch('/holiday-calendars/{holiday_calendar}/toggle-status', [HolidayCalendarController::class, 'toggleStatus'])
        ->name('holiday-calendars.toggle-status');
    Route::resource('holiday-calendars', HolidayCalendarController::class)->only(['index', 'store', 'update', 'destroy']);

    // Route aliases for convenience
    Route::get('/payroll/configurations', fn () => redirect()->route('payroll-configurations.index'))->name('payroll.configurations');
    Route::get('/payroll/holidays', fn () => redirect()->route('holiday-calendars.index'))->name('payroll.holidays');

    // Modul Payroll Security
    Route::get('/payroll-security', [SecurityPayrollController::class, 'index'])
        ->name('payroll-security.index');
    Route::post('/payroll-security/attendance', [SecurityPayrollController::class, 'updateAttendance'])
        ->name('payroll-security.attendance.update');
    Route::post('/payroll-security/summary', [SecurityPayrollController::class, 'updateSummary'])
        ->name('payroll-security.summary.update');
    Route::post('/payroll-security/config', [SecurityPayrollController::class, 'updateConfig'])
        ->name('payroll-security.config.update');
    Route::post('/payroll-security/periods/{period}/sync', [SecurityPayrollController::class, 'syncPayslips'])
        ->name('payroll-security.periods.sync');
    Route::get('/payroll-security/periods/{period}/export-excel', [SecurityPayrollController::class, 'exportExcel'])
        ->name('payroll-security.periods.export-excel');

    // Slip Gaji (Payslips Module)
    Route::resource('payslips', PayslipController::class);
    Route::get('/payslips/{payslip}/preview', [PayslipController::class, 'preview'])
        ->name('payslips.preview');
    Route::get('/payslips/{payslip}/download', [PayslipController::class, 'download'])
        ->name('payslips.download');
    Route::post('/payslips/{payslip}/finalize', [PayslipController::class, 'finalize'])
        ->name('payslips.finalize');
    Route::post('/payslips/{payslip}/void', [PayslipController::class, 'void'])
        ->name('payslips.void');

    // Alur Persetujuan Penggajian (Approval Workflow)
    Route::middleware('permission:approvals.view')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/periods/{payrollPeriod}', [ApprovalController::class, 'showPeriod'])->name('approvals.periods.show');
        Route::get('/approvals/periods/{payrollPeriod}/export-excel', [ApprovalController::class, 'exportExcel'])->name('approvals.periods.export-excel');
        Route::post('/approvals/periods/{payrollPeriod}/submit', [ApprovalController::class, 'submitPeriod'])->name('approvals.periods.submit');
        Route::post('/approvals/periods/{payrollPeriod}/approve', [ApprovalController::class, 'approvePeriod'])->name('approvals.periods.approve');
        Route::post('/approvals/periods/{payrollPeriod}/request-changes', [ApprovalController::class, 'requestPeriodChanges'])->name('approvals.periods.request-changes');

        Route::post('/approvals/payslips/{payslip}/submit', [ApprovalController::class, 'submitPayslip'])->name('approvals.payslips.submit');
        Route::post('/approvals/payslips/{payslip}/approve', [ApprovalController::class, 'approvePayslip'])->name('approvals.payslips.approve');
        Route::post('/approvals/payslips/{payslip}/request-changes', [ApprovalController::class, 'requestPayslipChanges'])->name('approvals.payslips.request-changes');
    });

    // Internal Users Management (protected by RBAC permissions)
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('users.store');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->middleware('permission:users.toggle-status')
            ->name('users.toggle-status');
    });

    // Roles and Permissions Matrix
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');

    // Pelacakan & Pengiriman Slip Gaji (Deliveries Module)
    Route::middleware('permission:deliveries.view')->group(function () {
        Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('/deliveries/bulk', [DeliveryController::class, 'bulkCreate'])->name('deliveries.bulk.create');
        Route::post('/deliveries/bulk/preview', [DeliveryController::class, 'bulkPreview'])->name('deliveries.bulk.preview');
        Route::post('/deliveries/bulk', [DeliveryController::class, 'bulkStore'])->name('deliveries.bulk.store');
        Route::post('/deliveries/retry-failed', [DeliveryController::class, 'retryFailed'])->name('deliveries.retry-failed');
        Route::post('/payslips/{payslip}/deliveries/email', [DeliveryController::class, 'sendEmail'])->name('payslips.deliveries.email');
        Route::post('/payslips/{payslip}/deliveries/whatsapp', [DeliveryController::class, 'sendWhatsApp'])->name('payslips.deliveries.whatsapp');
        Route::post('/payroll-periods/{payrollPeriod}/deliveries/email', [DeliveryController::class, 'sendPeriodEmails'])->name('payroll-periods.deliveries.email');
        Route::post('/payroll-periods/{payrollPeriod}/deliveries/whatsapp', [DeliveryController::class, 'sendPeriodWhatsApp'])->name('payroll-periods.deliveries.whatsapp');
        Route::post('/deliveries/{delivery}/retry', [DeliveryController::class, 'retry'])->name('deliveries.retry');
    });

    // Laporan Status Pengiriman & Rekapitulasi
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/deliveries', [ReportController::class, 'index'])->name('reports.deliveries.index');
        Route::get('/reports/deliveries/export', [ReportController::class, 'export'])
            ->middleware('permission:reports.export')
            ->name('reports.deliveries.export');
    });

    // Pengaturan Integrasi Server Email SMTP & WhatsApp Gateway
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/settings/email', [EmailSettingController::class, 'edit'])->name('settings.email.edit');
        Route::put('/settings/email', [EmailSettingController::class, 'update'])->name('settings.email.update');
        Route::post('/settings/email/test', [EmailSettingController::class, 'testConnection'])->name('settings.email.test');

        Route::get('/settings/whatsapp', [WhatsAppSettingController::class, 'edit'])->name('settings.whatsapp.edit');
        Route::put('/settings/whatsapp', [WhatsAppSettingController::class, 'update'])->name('settings.whatsapp.update');
        Route::post('/settings/whatsapp/test', [WhatsAppSettingController::class, 'testConnection'])->name('settings.whatsapp.test');
    });

    // Audit Logs Viewer
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-logs.view')
        ->name('audit-logs.index');
});

// Webhook callback endpoint for third-party WhatsApp gateway delivery reports
Route::post('/api/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.whatsapp');

// Temporary expiring HMAC signed URL for external WhatsApp gateway document retrieval
Route::get('/payslips/{payslip}/signed-pdf', [PayslipController::class, 'downloadSigned'])
    ->name('payslips.signed-pdf')
    ->middleware(['signed', 'throttle:60,1']);

require __DIR__.'/auth.php';
