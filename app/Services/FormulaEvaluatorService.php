<?php

namespace App\Services;

use InvalidArgumentException;

class FormulaEvaluatorService
{
    /**
     * Map of variable names to Indonesian human-friendly labels.
     */
    public const VARIABLE_LABELS = [
        'employee_overtime_rate' => 'Upah Lembur Karyawan',
        'overtime_days' => 'Jumlah Hari Lembur',
        'total_overtime_hours' => 'Total Jam Lembur',
        'first_hour_units' => 'Jam Pertama (Hari × 1)',
        'subsequent_overtime_hours' => 'Jam Berikutnya',
        'first_hour_multiplier' => 'Multiplier Jam Pertama',
        'subsequent_hour_multiplier' => 'Multiplier Jam Berikutnya',
        'holiday_overtime_multiplier' => 'Multiplier Lembur Hari Libur',
    ];

    /**
     * Evaluate an AST formula tree with given context variables.
     *
     * @param  array<string, mixed>  $formula
     * @param  array<string, mixed>  $context
     * @param  int  $depth
     * @return array{result: string, trace: array<int, string>, steps: array<string, mixed>}
     *
     * @throws InvalidArgumentException
     */
    public function evaluate(array $formula, array $context, int $depth = 0): array
    {
        if ($depth > 10) {
            throw new InvalidArgumentException('Kedalaman ekspresi formula melebihi batas maksimum (10 tingkat).');
        }

        $normalizedContext = $this->prepareContext($context);
        $steps = [];
        $trace = [];

        $resultNumber = $this->evaluateNode($formula, $normalizedContext, $steps, $trace, $depth);
        $formattedResult = number_format($resultNumber, 2, '.', '');

        return [
            'result' => $formattedResult,
            'trace' => $trace,
            'steps' => $steps,
            'inputs' => $normalizedContext,
        ];
    }

    /**
     * Recursively evaluate an individual AST node.
     */
    protected function evaluateNode(
        mixed $node,
        array &$context,
        array &$steps,
        array &$trace,
        int $depth
    ): float {
        // If node is a scalar constant
        if (is_numeric($node)) {
            return (float) $node;
        }

        // If node is a string variable name
        if (is_string($node)) {
            return $this->resolveVariable($node, $context);
        }

        if (! is_array($node)) {
            throw new InvalidArgumentException('Node formula tidak valid.');
        }

        // Handle explicit constant node
        if (isset($node['constant'])) {
            return (float) $node['constant'];
        }

        // Handle explicit variable node
        if (isset($node['variable'])) {
            $default = isset($node['default']) ? (float) $node['default'] : null;
            return $this->resolveVariable($node['variable'], $context, $default);
        }

        $type = strtolower($node['type'] ?? '');
        $operands = $node['operands'] ?? [];
        $nodeLabel = $node['label'] ?? $node['name'] ?? null;

        if (empty($operands)) {
            throw new InvalidArgumentException("Node formula tipe '{$type}' harus memiliki minimal 1 operand.");
        }

        // Evaluate child operands
        $evaluatedOperands = [];
        foreach ($operands as $idx => $child) {
            $evaluatedOperands[] = $this->evaluateNode($child, $context, $steps, $trace, $depth + 1);
        }

        $nodeResult = 0.0;

        switch ($type) {
            case '+':
            case 'add':
            case 'addition':
                $nodeResult = array_sum($evaluatedOperands);
                break;

            case '-':
            case 'subtract':
            case 'subtraction':
                $nodeResult = $evaluatedOperands[0];
                for ($i = 1; $i < count($evaluatedOperands); $i++) {
                    $nodeResult -= $evaluatedOperands[$i];
                }
                break;

            case '*':
            case '×':
            case 'multiply':
            case 'multiplication':
                $nodeResult = 1.0;
                foreach ($evaluatedOperands as $val) {
                    $nodeResult *= $val;
                }
                break;

            case '/':
            case '÷':
            case 'divide':
            case 'division':
                $nodeResult = $evaluatedOperands[0];
                for ($i = 1; $i < count($evaluatedOperands); $i++) {
                    if ((float) $evaluatedOperands[$i] === 0.0) {
                        throw new InvalidArgumentException('Pembagian dengan nol (division by zero) tidak diizinkan pada formula.');
                    }
                    $nodeResult /= $evaluatedOperands[$i];
                }
                break;

            case 'max':
                $nodeResult = max($evaluatedOperands);
                break;

            case 'min':
                $nodeResult = min($evaluatedOperands);
                break;

            default:
                throw new InvalidArgumentException("Operator formula '{$type}' tidak didukung.");
        }

        // If this node represents a named milestone (e.g. Jam Pertama, Jam Berikutnya)
        if ($nodeLabel) {
            $stepKey = $node['name'] ?? strtolower(str_replace(' ', '_', $nodeLabel));
            $steps[$stepKey] = [
                'label' => $nodeLabel,
                'amount' => number_format($nodeResult, 2, '.', ''),
            ];

            // Build human-friendly trace representation
            $traceString = $this->buildNodeTraceString($nodeLabel, $type, $evaluatedOperands, $nodeResult);
            if ($traceString) {
                $trace[] = $traceString;
            }
        }

        return $nodeResult;
    }

    /**
     * Resolve a variable from the context with fallback default.
     */
    protected function resolveVariable(string $name, array $context, ?float $default = null): float
    {
        if (array_key_exists($name, $context)) {
            return (float) $context[$name];
        }

        if ($default !== null) {
            return $default;
        }

        // Default zero for common variables if missing
        return 0.0;
    }

    /**
     * Prepare context and compute derived variables safely.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, float>
     */
    public function prepareContext(array $context): array
    {
        $rate = (float) ($context['employee_overtime_rate'] ?? $context['rate'] ?? 0);
        $days = (float) ($context['overtime_days'] ?? $context['days'] ?? 0);
        $totalHours = (float) ($context['total_overtime_hours'] ?? $context['hours'] ?? 0);

        // First hour units: 1 hour per overtime day worked, capped at totalHours
        $firstHourUnits = min(max($days * 1.0, 0.0), $totalHours);

        // Subsequent hours cannot be negative: max(total - first, 0)
        $subsequentHours = max($totalHours - $firstHourUnits, 0.0);

        $firstMultiplier = (float) ($context['first_hour_multiplier'] ?? 1.5);
        $subsequentMultiplier = (float) ($context['subsequent_hour_multiplier'] ?? 2.0);
        $holidayMultiplier = (float) ($context['holiday_overtime_multiplier'] ?? 2.0);

        return [
            'employee_overtime_rate' => $rate,
            'overtime_days' => $days,
            'total_overtime_hours' => $totalHours,
            'first_hour_units' => $firstHourUnits,
            'subsequent_overtime_hours' => $subsequentHours,
            'first_hour_multiplier' => $firstMultiplier,
            'subsequent_hour_multiplier' => $subsequentMultiplier,
            'holiday_overtime_multiplier' => $holidayMultiplier,
        ];
    }

    /**
     * Build an informative trace message for a calculation node.
     */
    protected function buildNodeTraceString(string $label, string $type, array $operands, float $result): string
    {
        $symbol = match ($type) {
            '+', 'add', 'addition' => '+',
            '-', 'subtract', 'subtraction' => '-',
            '*', '×', 'multiply', 'multiplication' => '×',
            '/', '÷', 'divide', 'division' => '÷',
            default => $type,
        };

        $formattedOperands = array_map(function ($val) {
            return (floor($val) == $val) ? number_format($val, 0, ',', '.') : number_format($val, 2, ',', '.');
        }, $operands);

        $formattedResult = number_format($result, 0, ',', '.');
        $opString = implode(" {$symbol} ", $formattedOperands);

        return "{$label}: {$opString} = Rp{$formattedResult}";
    }

    /**
     * Convert formula AST into human-readable Indonesian text preview.
     */
    public function toHumanReadable(array $formula, ?string $dayType = null): string
    {
        $type = strtolower($formula['type'] ?? '');
        $operands = $formula['operands'] ?? [];

        if (empty($operands)) {
            return '-';
        }

        // Preset detection for common Two-Stage Overtime (Lembur Hari Biasa)
        if ($this->isTwoStageOvertimeFormula($formula) || $dayType === 'working_day') {
            $threshold = isset($formula['break_threshold']) ? (float) $formula['break_threshold'] : 3.0;
            $duration = isset($formula['break_duration_per_day']) ? (float) $formula['break_duration_per_day'] : 0.5;
            $thresholdMin = (int) round($threshold * 60);
            $thresholdDisplay = ($threshold == (int) $threshold) ? (int) $threshold : $threshold;

            $firstMult = $this->extractMultiplier($operands[0] ?? [], 1.5);
            $subMult = $this->extractMultiplier($operands[1] ?? [], 2.0);

            return "LEMBUR HARI BIASA\n\n" .
                   "Potongan mulai berlaku pada lembur ≥ {$thresholdDisplay} jam.\n\n" .
                   "2:59  → Tidak dipotong\n" .
                   "3:00  → Dipotong\n" .
                   "3:01  → Dipotong\n" .
                   "4:00  → Dipotong\n\n" .
                   "Kriteria Teknis: overtime_minutes >= {$thresholdMin} (potongan = {$duration} jam per hari lembur)\n\n" .
                   "Rumus Perhitungan:\n" .
                   "Jam Pertama + Jam Berikutnya\n" .
                   "• Jam Pertama: Upah Lembur Karyawan × Jumlah Hari Lembur × {$firstMult}\n" .
                   "• Jam Berikutnya: (Total Jam Lembur Bersih - Jumlah Hari Lembur) × Upah Lembur Karyawan × {$subMult}";
        }

        // Preset detection for Holiday Overtime (Lembur Hari Libur)
        if ($this->isHolidayOvertimeFormula($formula) || in_array($dayType, ['holiday', 'national_holiday', 'joint_leave', 'weekend'], true)) {
            $threshold = isset($formula['break_threshold']) ? (float) $formula['break_threshold'] : 4.0;
            $duration = isset($formula['break_duration_per_day']) ? (float) $formula['break_duration_per_day'] : 1.0;
            $thresholdMin = (int) round($threshold * 60);
            $thresholdDisplay = ($threshold == (int) $threshold) ? (int) $threshold : $threshold;

            $mult = $this->extractMultiplier($formula, 2.0);

            return "LEMBUR HARI LIBUR\n\n" .
                   "Potongan mulai berlaku pada lembur ≥ {$thresholdDisplay} jam.\n\n" .
                   "3:59  → Tidak dipotong\n" .
                   "4:00  → Dipotong\n" .
                   "4:01  → Dipotong\n" .
                   "5:00  → Dipotong\n\n" .
                   "Kriteria Teknis: overtime_minutes >= {$thresholdMin} (potongan = {$duration} jam per hari lembur)\n\n" .
                   "Rumus Perhitungan:\n" .
                   "Total Jam Lembur Bersih × Upah Lembur Karyawan × {$mult}";
        }

        return $this->formatGenericNodeText($formula);
    }

    /**
     * Format a generic node recursively.
     */
    protected function formatGenericNodeText(mixed $node): string
    {
        if (is_numeric($node)) {
            return (string) $node;
        }

        if (is_string($node)) {
            return self::VARIABLE_LABELS[$node] ?? $node;
        }

        if (! is_array($node)) {
            return '';
        }

        if (isset($node['constant'])) {
            return (string) $node['constant'];
        }

        if (isset($node['variable'])) {
            $varName = $node['variable'];
            return self::VARIABLE_LABELS[$varName] ?? $varName;
        }

        $type = strtolower($node['type'] ?? '');
        $symbol = match ($type) {
            '+', 'add', 'addition' => '+',
            '-', 'subtract', 'subtraction' => '×',
            '*', '×', 'multiply', 'multiplication' => '×',
            '/', '÷', 'divide', 'division' => '÷',
            default => $type,
        };

        $parts = [];
        foreach ($node['operands'] ?? [] as $child) {
            $parts[] = $this->formatGenericNodeText($child);
        }

        $joined = implode(" {$symbol} ", $parts);
        return count($parts) > 1 ? "({$joined})" : $joined;
    }

    protected function isTwoStageOvertimeFormula(array $formula): bool
    {
        $type = strtolower($formula['type'] ?? '');
        $operands = $formula['operands'] ?? [];

        return in_array($type, ['+', 'add', 'addition'], true) && count($operands) === 2;
    }

    protected function isHolidayOvertimeFormula(array $formula): bool
    {
        $type = strtolower($formula['type'] ?? '');
        $operands = $formula['operands'] ?? [];

        return in_array($type, ['*', '×', 'multiply', 'multiplication'], true);
    }

    protected function extractMultiplier(array $node, float $fallback): float
    {
        foreach ($node['operands'] ?? [] as $op) {
            if (is_numeric($op)) return (float) $op;
            if (is_array($op) && isset($op['constant'])) return (float) $op['constant'];
            if (is_array($op) && isset($op['default'])) return (float) $op['default'];
        }
        return $fallback;
    }

    /**
     * Validate an AST formula tree and return a status array.
     *
     * @param  array  $formula
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateFormula(array $formula): array
    {
        try {
            $this->assertValidNode($formula);

            return [
                'valid' => true,
                'errors' => [],
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Recursively assert that a formula node is structurally valid.
     *
     * @throws InvalidArgumentException
     */
    public function assertValidNode(mixed $node): void
    {
        if (is_numeric($node) || is_string($node)) {
            return;
        }

        if (! is_array($node)) {
            throw new InvalidArgumentException('Node formula tidak valid.');
        }

        if (isset($node['constant']) || isset($node['variable'])) {
            return;
        }

        $type = strtolower($node['type'] ?? '');
        if (! in_array($type, ['+', 'add', 'addition', '-', 'subtract', 'subtraction', '*', '×', 'multiply', 'multiplication', '/', '÷', 'divide', 'division', 'max', 'min'], true)) {
            throw new InvalidArgumentException("Operator '{$type}' tidak valid.");
        }

        $operands = $node['operands'] ?? [];
        if (! is_array($operands) || count($operands) < 1) {
            throw new InvalidArgumentException('Formula harus memiliki setidaknya 1 operand.');
        }

        foreach ($operands as $child) {
            $this->assertValidNode($child);
        }
    }
}
