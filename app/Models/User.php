<?php

namespace App\Models;

use App\Domain\Store\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'store_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'role'      => UserRole::class,
    ];

    // ── Relationships ────────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isStoreOwner(): bool
    {
        return $this->role === UserRole::STORE_OWNER;
    }

    public function getStoreId(): int
    {
        return $this->store_id;
    }
}