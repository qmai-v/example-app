<?php

namespace App\Models\Enums;

enum TenantMemberRole: string
{
    case TenantAdmin = 'tenant_admin';
    case Member = 'member';

    public function isTenantAdmin(): bool
    {
        return $this === self::TenantAdmin;
    }
}
