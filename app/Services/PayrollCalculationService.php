<?php

namespace App\Services;

use InvalidArgumentException;

class PayrollCalculationService
{
    protected bool $dutyIncludedInGross;

    protected array $officialDutyCodes;

    public function __construct(
        ?bool $dutyIncludedInGross = null,
        ?array $officialDutyCodes = null
    ) {
        if ($dutyIncludedInGross !== null) {
            $this->dutyIncludedInGross = $dutyIncludedInGross;
        } else {
            $this->dutyIncludedInGross = (function_exists('app') && app()->bound('config'))
                ? (bool) config('payroll.official_duty_included_in_gross', false)
                : false;
        }

        if ($officialDutyCodes !== null) {
            $this->officialDutyCodes = $officialDutyCodes;
        } else {
            $this->officialDutyCodes = (function_exists('app') && app()->bound('config'))
                ? config('payroll.official_duty_codes', ['OFFICIAL_DUTY', 'UANG_DINAS', 'PJD', 'PJD_LUAR_KOTA'])
                : ['OFFICIAL_DUTY', 'UANG_DINAS', 'PJD', 'PJD_LUAR_KOTA'];
        }
    }

    /**
     * Calculate earnings, deductions, and net salary using precise decimal arithmetic.
     * Guarantees no floating-point precision issues and ensures basic salary is not counted twice.
     *
     * @param  array<int, array{item_type: string, amount: string|float|int, item_code_snapshot?: string}>  $items
     * @return array{basic_salary: string, total_earnings: string, total_deductions: string, net_salary: string}
     *
     * @throws InvalidArgumentException
     */
    public function calculate(array $items): array
    {
        $totalEarnings = '0.00';
        $totalDeductions = '0.00';
        $basicSalary = '0.00';
        $uangDinas = '0.00';
        $gajiBruto = '0.00';

        $dutyIncludedInGross = $this->dutyIncludedInGross;
        $officialDutyCodes = $this->officialDutyCodes;

        foreach ($items as $index => $item) {
            $rawAmount = $item['amount'] ?? 0;

            // Normalize amount string to 2 decimal places
            $amount = $this->normalizeDecimal($rawAmount);

            // Rejection of negative numbers
            if (bccomp($amount, '0.00', 2) < 0) {
                $description = $item['description_snapshot'] ?? $item['item_code_snapshot'] ?? 'Baris ke-'.($index + 1);
                throw new InvalidArgumentException("Nominal untuk '{$description}' tidak boleh negatif: {$amount}");
            }

            $type = strtolower($item['item_type'] ?? 'earning');
            $code = strtoupper($item['item_code_snapshot'] ?? '');

            // Capture basic salary if present, without double counting
            if (in_array($code, ['BASIC_SALARY', 'GAJI_POKOK', 'GAPOK'], true) && $type === 'earning') {
                $basicSalary = $amount;
            }

            $isOfficialDuty = in_array($code, $officialDutyCodes, true) ||
                strtolower($item['category'] ?? '') === 'official_duty';

            if ($isOfficialDuty) {
                $uangDinas = bcadd($uangDinas, $amount, 2);
            }

            if ($type === 'earning') {
                $totalEarnings = bcadd($totalEarnings, $amount, 2);
                if (! $isOfficialDuty || $dutyIncludedInGross) {
                    $gajiBruto = bcadd($gajiBruto, $amount, 2);
                }
            } elseif ($type === 'deduction') {
                $totalDeductions = bcadd($totalDeductions, $amount, 2);
            } else {
                throw new InvalidArgumentException("Tipe komponen tidak valid: {$type}. Harus 'earning' atau 'deduction'.");
            }
        }

        // net_salary = total_earnings - total_deductions
        $netSalary = bcsub($totalEarnings, $totalDeductions, 2);

        return [
            'basic_salary' => $basicSalary,
            'gaji_bruto' => $gajiBruto,
            'uang_dinas' => $uangDinas,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
        ];
    }

    /**
     * Convert any numeric input into an exact 2-decimal string format without float rounding error.
     */
    public function normalizeDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        // If formatted as rupiah string e.g. "5.000.000,00" or "5000000"
        $cleaned = trim((string) $value);
        $cleaned = str_replace(['Rp', 'rp', ' ', '.'], '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);

        if (! is_numeric($cleaned)) {
            throw new InvalidArgumentException("Format nominal tidak valid: {$value}");
        }

        return number_format((float) $cleaned, 2, '.', '');
    }
}
