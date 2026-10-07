<?php

namespace Tests\Unit;

use App\Services\PayrollCalculationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PayrollCalculationTest extends TestCase
{
    protected PayrollCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayrollCalculationService;
    }

    public function test_calculates_earnings_deductions_and_net_accurately(): void
    {
        $items = [
            [
                'item_code_snapshot' => 'BASIC_SALARY',
                'description_snapshot' => 'Gaji Pokok',
                'item_type' => 'earning',
                'amount' => '6500000.00',
            ],
            [
                'item_code_snapshot' => 'MEAL_ALLOWANCE',
                'description_snapshot' => 'Tunjangan Makan',
                'item_type' => 'earning',
                'amount' => '1250000.50',
            ],
            [
                'item_code_snapshot' => 'BPJS_TK',
                'description_snapshot' => 'BPJS Ketenagakerjaan',
                'item_type' => 'deduction',
                'amount' => '130000.00',
            ],
            [
                'item_code_snapshot' => 'PPH21',
                'description_snapshot' => 'PPh 21',
                'item_type' => 'deduction',
                'amount' => '250000.25',
            ],
        ];

        $result = $this->service->calculate($items);

        // Earnings = 6,500,000.00 + 1,250,000.50 = 7,750,000.50
        $this->assertEquals('7750000.50', $result['total_earnings']);

        // Deductions = 130,000.00 + 250,000.25 = 380,000.25
        $this->assertEquals('380000.25', $result['total_deductions']);

        // Net = 7,750,000.50 - 380,000.25 = 7,370,000.25
        $this->assertEquals('7370000.25', $result['net_salary']);

        // Basic salary captured in header
        $this->assertEquals('6500000.00', $result['basic_salary']);
    }

    public function test_basic_salary_is_not_counted_twice(): void
    {
        // When BASIC_SALARY is an earning item, it is part of total_earnings.
        // It must NOT be added an extra time to the net salary.
        $items = [
            [
                'item_code_snapshot' => 'BASIC_SALARY',
                'description_snapshot' => 'Gaji Pokok',
                'item_type' => 'earning',
                'amount' => '5000000.00',
            ],
            [
                'item_code_snapshot' => 'TRANSPORT',
                'description_snapshot' => 'Transport',
                'item_type' => 'earning',
                'amount' => '500000.00',
            ],
            [
                'item_code_snapshot' => 'BPJS',
                'description_snapshot' => 'BPJS',
                'item_type' => 'deduction',
                'amount' => '100000.00',
            ],
        ];

        $result = $this->service->calculate($items);

        $this->assertEquals('5000000.00', $result['basic_salary']);
        $this->assertEquals('5500000.00', $result['total_earnings']);
        $this->assertEquals('100000.00', $result['total_deductions']);
        // Net should be 5,500,000 - 100,000 = 5,400,000 (NOT 5,400,000 + 5,000,000)
        $this->assertEquals('5400000.00', $result['net_salary']);
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $items = [
            [
                'item_code_snapshot' => 'BASIC_SALARY',
                'description_snapshot' => 'Gaji Pokok',
                'item_type' => 'earning',
                'amount' => '-1000000.00',
            ],
        ];

        $this->service->calculate($items);
    }

    public function test_handles_string_decimal_normalization(): void
    {
        $this->assertEquals('5000000.00', $this->service->normalizeDecimal(5000000));
        $this->assertEquals('5000000.50', $this->service->normalizeDecimal('5000000.5'));
        $this->assertEquals('2500000.75', $this->service->normalizeDecimal('Rp 2.500.000,75'));
        $this->assertEquals('0.00', $this->service->normalizeDecimal(null));
        $this->assertEquals('0.00', $this->service->normalizeDecimal(''));
    }
}
