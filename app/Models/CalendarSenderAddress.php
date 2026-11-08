<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSenderAddress extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['verification_hash'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'consented_at' => 'datetime', 'verification_expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
