<?php

namespace App\Models;

use App\Models\Enums\TenantMemberRole;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['user_id', 'tenant_id', 'role'])]
class TenantMembership extends Pivot
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    protected $table = 'tenant_memberships';

    public $incrementing = true;

    public $timestamps = true;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === TenantMemberRole::TenantAdmin;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantMemberRole::class,
        ];
    }
}
