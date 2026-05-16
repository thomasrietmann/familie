<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_user_id',
        'public_token',
        'public_token_enabled',
        'dashboard_public_token',
        'dashboard_public_token_enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'public_token_enabled' => 'boolean',
            'dashboard_public_token_enabled' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_users')
            ->withPivot(['role', 'status', 'invited_at', 'accepted_at'])
            ->withTimestamps();
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FamilyEvent::class);
    }

    public function documentImports(): HasMany
    {
        return $this->hasMany(DocumentImport::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function activeParents(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'active');
    }

    public function hasPublicToken(): bool
    {
        return $this->public_token_enabled && filled($this->public_token);
    }

    public function publicUrl(): ?string
    {
        return $this->hasPublicToken() ? route('public.family', $this->public_token) : null;
    }

    public function generatePublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('public_token', $token)->exists());

        $this->forceFill([
            'public_token' => $token,
            'public_token_enabled' => true,
        ])->save();

        return $token;
    }

    public function disablePublicToken(): void
    {
        $this->forceFill(['public_token_enabled' => false])->save();
    }

    public function hasDashboardPublicToken(): bool
    {
        return $this->dashboard_public_token_enabled && filled($this->dashboard_public_token);
    }

    public function dashboardPublicUrl(): ?string
    {
        return $this->hasDashboardPublicToken() ? route('public.dashboard', $this->dashboard_public_token) : null;
    }

    public function generateDashboardPublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('dashboard_public_token', $token)->exists());

        $this->forceFill([
            'dashboard_public_token' => $token,
            'dashboard_public_token_enabled' => true,
        ])->save();

        return $token;
    }

    public function disableDashboardPublicToken(): void
    {
        $this->forceFill(['dashboard_public_token_enabled' => false])->save();
    }
}
