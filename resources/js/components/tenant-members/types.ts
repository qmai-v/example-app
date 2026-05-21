import type { GenericPagination } from '@/types';
import type { TenantRole } from '@/types/auth';

export type TenantMemberRow = {
    id: number;
    user: { id: number; name: string; email: string };
    role: TenantRole;
    created_at: string;
};

export type TenantMemberFilters = {
    search: string | null;
    role: TenantRole | null;
    per_page: number;
    per_page_options: number[];
};

export type TenantMemberPagination = GenericPagination<TenantMemberRow>;

export type TenantMemberIndexQuery = {
    search?: string;
    role?: TenantRole;
    per_page: number;
};

export type TenantMemberRoutes = {
    indexUrl: (query: TenantMemberIndexQuery) => string;
    storeUrl: () => string;
    updateUrl: (member: TenantMemberRow) => string;
    destroyUrl: (member: TenantMemberRow) => string;
};
