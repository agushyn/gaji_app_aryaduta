<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'office_id',
        'department_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relationship: A division belongs to an office.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Relationship: A division belongs to a department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship: A division has many employees.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Scope for active divisions only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for searching divisions by name or code.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Scope for filtering by office.
     */
    public function scopeFilterOffice(Builder $query, ?int $officeId): Builder
    {
        if (empty($officeId)) {
            return $query;
        }

        return $query->where('office_id', $officeId);
    }

    /**
     * Scope for filtering by department.
     */
    public function scopeFilterDepartment(Builder $query, ?int $departmentId): Builder
    {
        if (empty($departmentId)) {
            return $query;
        }

        return $query->where('department_id', $departmentId);
    }

    /**
     * Determine if division can be safely deleted.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->employees()->exists();
    }

    /**
     * String representation of division.
     */
    public function __toString(): string
    {
        return (string) $this->name;
    }
}
