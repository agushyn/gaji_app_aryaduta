<?php

namespace App\Services;

use App\Models\PayComponent;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayComponentService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Create a new pay component.
     */
    public function createComponent(array $data): PayComponent
    {
        return DB::transaction(function () use ($data) {
            $component = PayComponent::create([
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'type' => strtolower(trim($data['type'])),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'created_by' => Auth::id(),
            ]);

            $this->auditLogService->log(
                action: 'pay_component.created',
                subject: $component,
                changes: [
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'is_active' => $component->is_active,
                    'sort_order' => $component->sort_order,
                ]
            );

            return $component;
        });
    }

    /**
     * Update an existing pay component.
     */
    public function updateComponent(PayComponent $component, array $data): PayComponent
    {
        return DB::transaction(function () use ($component, $data) {
            $original = $component->only(['code', 'name', 'type', 'is_active', 'sort_order']);

            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim($data['code']));
            }
            if (isset($data['name'])) {
                $data['name'] = trim($data['name']);
            }
            if (isset($data['type'])) {
                $data['type'] = strtolower(trim($data['type']));
            }
            if (isset($data['sort_order'])) {
                $data['sort_order'] = (int) $data['sort_order'];
            }

            $component->fill($data)->save();

            $changes = [];
            foreach ($data as $key => $newVal) {
                $oldVal = $original[$key] ?? null;
                if ($oldVal != $newVal) {
                    $changes[$key] = [
                        'from' => $oldVal,
                        'to' => $newVal,
                    ];
                }
            }

            if (! empty($changes)) {
                $this->auditLogService->log(
                    action: 'pay_component.updated',
                    subject: $component,
                    changes: $changes
                );
            }

            return $component;
        });
    }

    /**
     * Toggle active status of a pay component.
     */
    public function toggleActive(PayComponent $component): PayComponent
    {
        return DB::transaction(function () use ($component) {
            $previous = $component->is_active;
            $component->is_active = ! $previous;
            $component->save();

            $this->auditLogService->log(
                action: 'pay_component.status_toggled',
                subject: $component,
                changes: [
                    'previous_status' => $previous ? 'active' : 'inactive',
                    'new_status' => $component->is_active ? 'active' : 'inactive',
                ]
            );

            return $component;
        });
    }

    /**
     * Delete a pay component.
     * Prevents physical deletion if used in any payslips.
     */
    public function deleteComponent(PayComponent $component): void
    {
        if ($component->isUsedInPayslips()) {
            throw new DomainException(
                "Komponen '{$component->name}' ({$component->code}) telah digunakan dalam data slip gaji sehingga tidak dapat dihapus secara fisik. Silakan nonaktifkan komponen ini agar tidak muncul lagi pada penginputan baru."
            );
        }

        DB::transaction(function () use ($component) {
            $info = [
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
            ];

            $component->delete();

            $this->auditLogService->log(
                action: 'pay_component.deleted',
                subject: PayComponent::class,
                changes: $info
            );
        });
    }
}
