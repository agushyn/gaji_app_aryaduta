<?php

namespace App\Models;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'full_name',
        'office_id',
        'department_id',
        'division_id',
        'position_id',
        'department',
        'position',
        'email',
        'phone',
        'hire_date',
        'employment_status',
        'gaji_pokok',
        'tunjangan_kehadiran',
        'tunjangan_keluarga',
        'tunjangan_jabatan',
        'uang_makan_per_hari',
        'uang_transportasi_per_hari',
        'bpjs_ketenagakerjaan_enabled',
        'bpjs_ketenagakerjaan_amount',
        'default_overtime_rate',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'office_id' => 'integer',
            'department_id' => 'integer',
            'division_id' => 'integer',
            'position_id' => 'integer',
            'bpjs_ketenagakerjaan_enabled' => 'boolean',
            'gaji_pokok' => 'decimal:2',
            'tunjangan_kehadiran' => 'decimal:2',
            'tunjangan_keluarga' => 'decimal:2',
            'tunjangan_jabatan' => 'decimal:2',
            'uang_makan_per_hari' => 'decimal:2',
            'uang_transportasi_per_hari' => 'decimal:2',
            'bpjs_ketenagakerjaan_amount' => 'decimal:2',
            'default_overtime_rate' => 'decimal:2',
        ];
    }

    /**
     * Build default salary items for payslip generation based on master employee data and working days.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaultSalaryItems(int $workingDays = 25): array
    {
        $items = [];
        $order = 1;

        // 1. Gaji Pokok (Fixed Earning)
        if (bccomp((string) ($this->gaji_pokok ?? 0), '0.00', 2) > 0) {
            $items[] = [
                'item_code_snapshot' => 'BASIC_SALARY',
                'description_snapshot' => 'Gaji Pokok',
                'item_type' => 'earning',
                'category' => 'fixed',
                'amount' => number_format((float) $this->gaji_pokok, 2, '.', ''),
                'sort_order' => $order++,
            ];
        }

        // 2. Tunjangan Jabatan (Fixed Earning)
        if (bccomp((string) ($this->tunjangan_jabatan ?? 0), '0.00', 2) > 0) {
            $items[] = [
                'item_code_snapshot' => 'POSITION_ALLOWANCE',
                'description_snapshot' => 'Tunjangan Jabatan',
                'item_type' => 'earning',
                'category' => 'fixed',
                'amount' => number_format((float) $this->tunjangan_jabatan, 2, '.', ''),
                'sort_order' => $order++,
            ];
        }

        // 3. Tunjangan Kehadiran (Fixed Earning)
        if (bccomp((string) ($this->tunjangan_kehadiran ?? 0), '0.00', 2) > 0) {
            $items[] = [
                'item_code_snapshot' => 'ATTENDANCE_INCENTIVE',
                'description_snapshot' => 'Tunjangan Kehadiran',
                'item_type' => 'earning',
                'category' => 'fixed',
                'amount' => number_format((float) $this->tunjangan_kehadiran, 2, '.', ''),
                'sort_order' => $order++,
            ];
        }

        // 4. Tunjangan Keluarga (Fixed Earning)
        if (bccomp((string) ($this->tunjangan_keluarga ?? 0), '0.00', 2) > 0) {
            $items[] = [
                'item_code_snapshot' => 'FAMILY_ALLOWANCE',
                'description_snapshot' => 'Tunjangan Keluarga',
                'item_type' => 'earning',
                'category' => 'fixed',
                'amount' => number_format((float) $this->tunjangan_keluarga, 2, '.', ''),
                'sort_order' => $order++,
            ];
        }

        // 5. Uang Makan (Daily Rate x Working Days)
        if (bccomp((string) ($this->uang_makan_per_hari ?? 0), '0.00', 2) > 0) {
            $rateStr = number_format((float) $this->uang_makan_per_hari, 2, '.', '');
            $totalMeal = bcmul($rateStr, (string) $workingDays, 2);

            $items[] = [
                'item_code_snapshot' => 'MEAL_ALLOWANCE',
                'description_snapshot' => 'Uang Makan',
                'item_type' => 'earning',
                'category' => 'daily_allowance',
                'rate' => $rateStr,
                'quantity' => (string) $workingDays,
                'unit' => 'hari',
                'amount' => $totalMeal,
                'sort_order' => $order++,
            ];
        }

        // 6. Uang Transport (Daily Rate x Working Days)
        if (bccomp((string) ($this->uang_transportasi_per_hari ?? 0), '0.00', 2) > 0) {
            $rateStr = number_format((float) $this->uang_transportasi_per_hari, 2, '.', '');
            $totalTransport = bcmul($rateStr, (string) $workingDays, 2);

            $items[] = [
                'item_code_snapshot' => 'TRANSPORT_ALLOWANCE',
                'description_snapshot' => 'Uang Transport',
                'item_type' => 'earning',
                'category' => 'daily_allowance',
                'rate' => $rateStr,
                'quantity' => (string) $workingDays,
                'unit' => 'hari',
                'amount' => $totalTransport,
                'sort_order' => $order++,
            ];
        }

        // 7. Potongan BPJS Ketenagakerjaan (Deduction, only if enabled)
        if ($this->bpjs_ketenagakerjaan_enabled && bccomp((string) ($this->bpjs_ketenagakerjaan_amount ?? 0), '0.00', 2) > 0) {
            $items[] = [
                'item_code_snapshot' => 'BPJS_TK',
                'description_snapshot' => 'BPJS Ketenagakerjaan',
                'item_type' => 'deduction',
                'category' => 'insurance',
                'amount' => number_format((float) $this->bpjs_ketenagakerjaan_amount, 2, '.', ''),
                'sort_order' => 200,
            ];
        }

        return $items;
    }

    /**
     * Check if the employee status is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->employment_status === 'active';
    }

    /**
     * Formatted phone number for UI display.
     */
    public function getFormattedPhoneAttribute(): string
    {
        return PhoneNumberNormalizer::toDisplayFormat($this->phone);
    }

    /**
     * Relationship: An employee belongs to an office.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    /**
     * Relationship: An employee belongs to a department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Accessor for department: returns relation when loaded, otherwise attribute value.
     */
    public function getDepartmentAttribute($value)
    {
        if ($this->relationLoaded('department')) {
            return $this->getRelationValue('department');
        }

        return $value;
    }

    /**
     * Relationship: An employee belongs to a division.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * Relationship: An employee belongs to a position.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Accessor for position: returns relation when loaded, otherwise attribute value.
     */
    public function getPositionAttribute($value)
    {
        if ($this->relationLoaded('position')) {
            return $this->getRelationValue('position');
        }

        return $value;
    }

    /**
     * Relationship with payslips.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Check if the employee has any payslip history.
     */
    public function hasPayslipHistory(): bool
    {
        if (! Schema::hasTable('payslips')) {
            return false;
        }

        return DB::table('payslips')
            ->where('employee_id', $this->id)
            ->exists();
    }

    /**
     * Scope for searching employees by name, employee number, email, office, division, or position.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('employee_number', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('department', 'like', "%{$term}%")
                ->orWhere('position', 'like', "%{$term}%")
                ->orWhereHas('office', fn ($oq) => $oq->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                ->orWhereHas('division', fn ($divQ) => $divQ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                ->orWhereHas('position', fn ($posQ) => $posQ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                ->orWhereHas('department', fn ($dq) => $dq->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        });
    }

    /**
     * Scope for filtering by office.
     */
    public function scopeFilterOffice(Builder $query, mixed $office): Builder
    {
        if (empty($office) || $office === 'all') {
            return $query;
        }

        if (is_numeric($office)) {
            return $query->where('office_id', (int) $office);
        }

        return $query->whereHas('office', fn ($oq) => $oq->where('name', $office)->orWhere('code', $office));
    }

    /**
     * Scope for filtering by position.
     */
    public function scopeFilterPosition(Builder $query, mixed $position): Builder
    {
        if (empty($position) || $position === 'all') {
            return $query;
        }

        if (is_numeric($position)) {
            return $query->where('position_id', (int) $position);
        }

        return $query->where(function (Builder $q) use ($position) {
            $q->where('position', $position)
                ->orWhereHas('position', fn ($pq) => $pq->where('name', $position)->orWhere('code', $position));
        });
    }

    /**
     * Scope for filtering by department.
     */
    public function scopeFilterDepartment(Builder $query, mixed $department): Builder
    {
        if (empty($department) || $department === 'all') {
            return $query;
        }

        if (is_numeric($department)) {
            return $query->where('department_id', (int) $department);
        }

        return $query->where(function (Builder $q) use ($department) {
            $q->where('department', $department)
                ->orWhereHas('department', fn ($dq) => $dq->where('name', $department)->orWhere('code', $department));
        });
    }

    /**
     * Scope for filtering by division.
     */
    public function scopeFilterDivision(Builder $query, mixed $division): Builder
    {
        if (empty($division) || $division === 'all') {
            return $query;
        }

        if (is_numeric($division)) {
            return $query->where('division_id', (int) $division);
        }

        return $query->whereHas('division', fn ($dq) => $dq->where('name', $division)->orWhere('code', $division));
    }

    /**
     * Scope for filtering by employment status.
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || $status === 'all') {
            return $query;
        }

        return $query->where('employment_status', $status);
    }

    /**
     * Scope for active employees only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', 'active');
    }
}
