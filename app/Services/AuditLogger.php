<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Family;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** Only field names are stored, never values, request payloads, IPs or secrets. */
    public function record(string $action, ?Model $entity = null, array $fields = [], ?int $familyId = null): void
    {
        AuditLog::create([
            'action' => $action,
            'family_id' => $familyId ?? ($entity instanceof Family ? $entity->getKey() : $entity?->getAttribute('family_id')),
            'user_id' => auth()->id(),
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'changed_fields' => array_values(array_intersect($fields, [
                'name', 'first_name', 'last_name', 'birthdate', 'notes', 'title', 'description',
                'starts_at', 'ends_at', 'all_day', 'location', 'category', 'visibility',
                'owner_type', 'owner_id', 'status', 'member_color', 'target_type', 'target_id',
                'public_token_enabled', 'dashboard_public_token_enabled',
            ])),
        ]);
    }
}
