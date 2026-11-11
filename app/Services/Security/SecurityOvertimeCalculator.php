<?php

namespace App\Services\Security;

use App\Models\SecurityPayrollConfig;

class SecurityOvertimeCalculator
{
    /**
     * Calculate REGULAR overtime based on verified Excel formula:
     * - Break deduction = overtime_hours / 8 (Excel cell AV8)
     * - Net overtime = overtime_hours - break_deduction (Excel cell AW8)
     * - First hours = min(net_hours, overtime_days) (Excel cell AZ8)
     * - Subsequent hours = max(0, net_hours - first_hours) (Excel cell BB8)
     * - First hours cost = first_hours * hourly_rate * 1.5 (Excel cell BA8)
     * - Subsequent hours cost = subsequent_hours * hourly_rate * 2.0 (Excel cell BC8)
     * - Total cost = first_hours_cost + subsequent_hours_cost (Excel cell BD8)
     *
     * @return array{
     *     overtime_days: int,
     *     gross_hours: float,
     *     break_deduction_hours: float,
     *     net_hours: float,
     *     hourly_rate: float,
     *     first_hours_limit: float,
     *     first_hours_multiplier: float,
     *     first_hours: float,
     *     first_hours_cost: float,
     *     subsequent_hours_multiplier: float,
     *     subsequent_hours: float,
     *     subsequent_hours_cost: float,
     *     total_cost: float
     * }
     */
    public function calculateRegularOvertime(
        int $overtimeDays,
        float $adjustedOvertimeHours,
        SecurityPayrollConfig $config,
        ?float $overrideBreakDeduction = null
    ): array {
        $days = max(0, $overtimeDays);
        $gross = max(0.0, round($adjustedOvertimeHours, 2));

        // Determine break deduction according to config rule or explicit override
        if ($overrideBreakDeduction !== null) {
            $deduction = max(0.0, round($overrideBreakDeduction, 2));
        } elseif ($config->break_deduction_rule === 'per_8_hours' || $config->break_deduction_rule === 'excel') {
            $deduction = round($gross / 8.0, 2);
        } elseif ($config->break_deduction_rule === 'none') {
            $deduction = 0.0;
        } else {
            // 'fixed' rule: use break_deduction_default from config
            $deduction = max(0.0, (float) $config->break_deduction_default);
        }

        if ($config->max_break_deduction > 0) {
            $deduction = min($deduction, (float) $config->max_break_deduction);
        }

        // Net overtime hours = max(0, gross - deduction)
        $net = max(0.0, round($gross - $deduction, 2));

        // In Excel, first hours is count of overtime days: min(net, overtime_days)
        // If overtime_days is 0 but gross hours exist, fallback to config first_hours_limit or net
        $firstHoursLimit = $days > 0 ? (float) $days : min((float) $config->first_hours_limit, $net);
        $firstHours = min($net, $firstHoursLimit);
        $subsequentHours = max(0.0, round($net - $firstHours, 2));

        $hourlyRate = (string) $config->hourly_rate;
        $multFirst = (string) $config->first_hours_multiplier;
        $multSubsequent = (string) $config->subsequent_hours_multiplier;

        // BCMath precision calculations
        $firstHoursCostRaw = bcmul(bcmul((string) $firstHours, $hourlyRate, 4), $multFirst, 4);
        $firstHoursCost = (float) $this->applyRounding($firstHoursCostRaw, $config->round_rule);

        $subsequentHoursCostRaw = bcmul(bcmul((string) $subsequentHours, $hourlyRate, 4), $multSubsequent, 4);
        $subsequentHoursCost = (float) $this->applyRounding($subsequentHoursCostRaw, $config->round_rule);

        $totalCost = (float) bcadd((string) $firstHoursCost, (string) $subsequentHoursCost, 2);

        return [
            'overtime_days' => $days,
            'gross_hours' => $gross,
            'break_deduction_hours' => $deduction,
            'net_hours' => $net,
            'hourly_rate' => (float) $hourlyRate,
            'first_hours_limit' => $firstHoursLimit,
            'first_hours_multiplier' => (float) $multFirst,
            'first_hours' => $firstHours,
            'first_hours_cost' => (float) $firstHoursCost,
            'subsequent_hours_multiplier' => (float) $multSubsequent,
            'subsequent_hours' => $subsequentHours,
            'subsequent_hours_cost' => (float) $subsequentHoursCost,
            'total_cost' => (float) $totalCost,
        ];
    }

    /**
     * Calculate OFF_HOLIDAY overtime independently.
     * Enforces the business constraint: Never invent tariffs. If tariff is not confirmed,
     * do not produce an arbitrary final nominal cost.
     *
     * @return array{
     *     overtime_days: int,
     *     gross_hours: float,
     *     break_deduction_hours: float,
     *     net_hours: float,
     *     hourly_rate: float,
     *     multiplier: float,
     *     total_cost: float,
     *     rate_confirmed: bool,
     *     rate_status_message: ?string,
     *     estimated_cost: float
     * }
     */
    public function calculateOffHolidayOvertime(
        int $overtimeDays,
        float $overtimeHours,
        SecurityPayrollConfig $config,
        bool $rateConfirmed = false
    ): array {
        $days = max(0, $overtimeDays);
        $gross = max(0.0, round($overtimeHours, 2));

        // Break deduction for holiday shifts (1h per 8h shift)
        $deduction = round($gross / 8.0, 2);
        if ($config->max_break_deduction > 0) {
            $deduction = min($deduction, (float) $config->max_break_deduction);
        }

        $net = max(0.0, round($gross - $deduction, 2));

        // Multiplier: holiday multiplier from config (if > 1.0) or standard 2.0x
        $multiplierFloat = (float) $config->holiday_multiplier > 1.0
            ? (float) $config->holiday_multiplier
            : 2.00;
        $multiplierStr = number_format($multiplierFloat, 2, '.', '');
        $hourlyRate = (string) $config->hourly_rate;

        // Estimated cost based on standard 2.0x / config
        $estimatedCostRaw = bcmul(bcmul((string) $net, $hourlyRate, 4), $multiplierStr, 4);
        $estimatedCost = (float) $this->applyRounding($estimatedCostRaw, $config->round_rule);

        if ($gross <= 0.0) {
            return [
                'overtime_days' => $days,
                'gross_hours' => 0.0,
                'break_deduction_hours' => 0.0,
                'net_hours' => 0.0,
                'hourly_rate' => (float) $hourlyRate,
                'multiplier' => $multiplierFloat,
                'total_cost' => 0.0,
                'rate_confirmed' => true,
                'rate_status_message' => null,
                'estimated_cost' => 0.0,
            ];
        }

        // If rate is explicitly confirmed by company policy, compute official cost
        if ($rateConfirmed) {
            return [
                'overtime_days' => $days,
                'gross_hours' => $gross,
                'break_deduction_hours' => $deduction,
                'net_hours' => $net,
                'hourly_rate' => (float) $hourlyRate,
                'multiplier' => $multiplierFloat,
                'total_cost' => $estimatedCost,
                'rate_confirmed' => true,
                'rate_status_message' => 'Tarif terkonfirmasi.',
                'estimated_cost' => $estimatedCost,
            ];
        }

        // If not confirmed, total_cost is 0.0 to prevent unauthorized payout, while providing clear status indicator
        return [
            'overtime_days' => $days,
            'gross_hours' => $gross,
            'break_deduction_hours' => $deduction,
            'net_hours' => $net,
            'hourly_rate' => (float) $hourlyRate,
            'multiplier' => $multiplierFloat,
            'total_cost' => 0.0,
            'rate_confirmed' => false,
            'rate_status_message' => "Menunggu konfirmasi tarif lembur OFF/Libur dari manajemen (Estimasi PP 35/2021: {$multiplierFloat}x upah/jam = Rp " . number_format($estimatedCost, 0, ',', '.') . ').',
            'estimated_cost' => $estimatedCost,
        ];
    }

    /**
     * Apply rounding rule using BCMath/math functions.
     */
    protected function applyRounding(string $amount, string $rule = 'round'): string
    {
        $floatVal = (float) $amount;

        $rounded = match ($rule) {
            'ceil' => ceil($floatVal),
            'floor' => floor($floatVal),
            default => round($floatVal),
        };

        return number_format($rounded, 2, '.', '');
    }
}
