<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalculationConfigVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_id',
        'version',
        'formula',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'formula' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent configuration.
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationConfig::class, 'config_id');
    }

    /**
     * Creator of this version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
