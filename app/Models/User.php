<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'id',
        'name',
        'email',
    ];

    protected $hidden = [
        'remember_token',
    ];

    public function storeRoles(): HasMany
    {
        return $this->hasMany(UserStoreRole::class);
    }

    public function hasStoreRole(string $roleName, ?int $storeId = null): bool
    {
        // ✅ super-admin: has everything
        if ($this->isSuperAdmin()) {
            return true;
        }

        $roleName = trim($roleName);
        if ($roleName === '')
            return false;

        $q = $this->storeRoles()
            ->where('active', true)
            ->where('role_name', $roleName);

        if ($storeId === null) {
            return $q->whereNull('store_id')->exists();
        }

        return $q->where(function ($qq) use ($storeId) {
            $qq->whereNull('store_id')->orWhere('store_id', (int) $storeId);
        })->exists();
    }
}
