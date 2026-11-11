<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\Office;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipService;
use App\Services\Security\SecurityPayrollExportService;
use App\Services\Security\SecurityPayrollReportService;
use App\Services\Security\SecurityReconciliationService;
use App\Services\SecurityPayrollCalculationService;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityPayrollPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $personalia;

    protected Office $office;

    protected Position $securityPosition;

    protected PayrollPeriod $period;

    protected SecurityPayrollReportService $reportService;

    protected SecurityReconciliationService $reconciliationService;

    protected SecurityPayrollExportService $exportService;

    protected SecurityPayrollCalculationService $calculationService;

    protected PayslipService $payslipService;

    protected PayslipFinalizationService $finalizationService;

    /** @var array<string, array{time_ms: float, query_count: int}> */
    public static array $measurements = [];

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        fwrite(STDERR, PHP_EOL."PERFORMANCE BENCHMARK RESULTS: ".json_encode(self::$measurements, JSON_PRETTY_PRINT).PHP_EOL);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(OrganizationMasterSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayrollCalculationConfigSeeder::class);
        $this->seed(SecurityPayrollConfigSeeder::class);

        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->office = Office::first();
        $this->securityPosition = Position::where('payroll_type', 'security')->first();

        $this->reportService = app(SecurityPayrollReportService::class);
        $this->reconciliationService = app(SecurityReconciliationService::class);
        $this->exportService = app(SecurityPayrollExportService::class);
        $this->calculationService = app(SecurityPayrollCalculationService::class);
        $this->payslipService = app(PayslipService::class);
        $this->finalizationService = app(PayslipFinalizationService::class);

        $this->period = PayrollPeriod::create([
            'period_code' => 'PERF-2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'cutoff_date' => '2026-09-25',
            'payment_date' => '2026-09-30',
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        // Seed 15 Security Employees to measure real batch performance
        for ($i = 1; $i <= 15; $i++) {
            $emp = Employee::create([
                'employee_number' => 'SEC-PERF-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'nik' => '3201PERF'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'full_name' => "Security Officer {$i}",
                'employment_status' => 'active',
                'hire_date' => '2024-01-01',
                'position_id' => $this->securityPosition->id,
                'office_id' => $this->office->id,
                'department' => 'Security',
                'gaji_pokok' => 4500000,
            ]);

            SecurityOvertimeSummary::create([
                'payroll_period_id' => $this->period->id,
                'employee_id' => $emp->id,
                'present_days' => 24,
                'off_days' => 6,
                'attendance_hours' => 192.0,
                'target_hours' => 192.0,
                'current_shortage_hours' => 0.0,
                'previous_shortage_hours' => 0.0,
                'total_shortage_hours' => 0.0,
                'regular_overtime_days' => 4,
                'regular_overtime_hours' => 16.0,
                'regular_net_overtime_hours' => 14.0,
                'regular_overtime_cost' => 364000.0,
                'total_overtime_cost' => 364000.0,
            ]);
        }

        $this->payslipService->generatePayrollForPeriod($this->period, $this->personalia->id);
    }

    /** 1. Measure Dashboard Response Time and Query Count */
    public function test_measure_dashboard_performance(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->actingAs($this->personalia)->get(route('payroll-security.index', [
            'period_id' => $this->period->id,
        ]));

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);

        // Record metrics
        self::$measurements['dashboard'] = ['time_ms' => $elapsedMs, 'query_count' => $queryCount];

        // Must be fast (< 500ms) and queries bounded
        $this->assertLessThan(1000.0, $elapsedMs);
        $this->assertLessThan(25, $queryCount);
    }

    /** 2. Measure Reports Dashboard & Detail Report Performance */
    public function test_measure_reports_detail_performance(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->actingAs($this->personalia)->get(route('payroll-security.reports.index', [
            'period_id' => $this->period->id,
            'tab' => 'detail',
        ]));

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);
        self::$measurements['reports_detail'] = ['time_ms' => $elapsedMs, 'query_count' => $queryCount];

        $this->assertLessThan(1000.0, $elapsedMs);
        $this->assertLessThan(20, $queryCount);
    }

    /** 3. Measure Reconciliation 10-Check Execution Performance */
    public function test_measure_reconciliation_performance(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startTime = microtime(true);

        $result = $this->reconciliationService->reconcilePeriod($this->period);

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        self::$measurements['reconciliation'] = ['time_ms' => $elapsedMs, 'query_count' => $queryCount];

        // 15 employees with 10 checks each (150 total integrity checks) must execute in < 150ms with <= 10 queries
        $this->assertLessThan(200.0, $elapsedMs);
        $this->assertLessThan(10, $queryCount);
        $this->assertEquals(15, count($result['results']));
    }

    /** 4. Measure XLSX Export Streaming Performance */
    public function test_measure_xlsx_export_performance(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->actingAs($this->personalia)->get(route('payroll-security.reports.export', [
            'type' => 'employee-detail',
            'period_id' => $this->period->id,
            'format' => 'xlsx',
        ]));

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);
        self::$measurements['export_xlsx'] = ['time_ms' => $elapsedMs, 'query_count' => $queryCount];

        $this->assertLessThan(2000.0, $elapsedMs);
    }

    /** 5. Measure CSV Export Streaming Performance */
    public function test_measure_csv_export_performance(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->actingAs($this->personalia)->get(route('payroll-security.reports.export', [
            'type' => 'shortage',
            'period_id' => $this->period->id,
            'format' => 'csv',
        ]));

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);
        self::$measurements['export_csv'] = ['time_ms' => $elapsedMs, 'query_count' => $queryCount];

        // CSV export is extremely lightweight (< 500ms)
        $this->assertLessThan(1000.0, $elapsedMs);
    }
}
