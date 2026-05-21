export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isSuperAdmin: boolean;
};

export type TenantRole = 'tenant_admin' | 'member';

export type TenantStatus = 'active' | 'suspended';

export type ActiveTenant = {
    id: string;
    name: string;
    slug: string;
    status: TenantStatus;
};

export type AvailableTenant = {
    id: string;
    name: string;
    slug: string;
    role: TenantRole | 'super_admin';
};

export type SharedTenant = {
    active: ActiveTenant | null;
    role: TenantRole | null;
    actingAsSuperAdmin: boolean;
    available: AvailableTenant[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */
