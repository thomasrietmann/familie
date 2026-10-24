<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['family_id', 'user_id', 'action', 'entity_type', 'entity_id', 'changed_fields'];

    protected function casts(): array
    {
        return ['changed_fields' => 'array'];
    }
}
