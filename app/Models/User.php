<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Enums\TenantMemberRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function lastTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'last_tenant_id');
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->memberships()
            ->where('tenant_id', $tenant->getKey())
            ->exists();
    }

    public function roleInTenant(Tenant $tenant): ?TenantMemberRole
    {
        /** @var TenantMembership|null $membership */
        $membership = $this->memberships()
            ->where('tenant_id', $tenant->getKey())
            ->first();

        return $membership?->role;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }
}
