import type { GenericIndexProps, UserRow } from '@/types';

type UserFilters = {
    per_page: number;
    per_page_options: number[];
    search: string | null;
    status: Exclude<UserStatusFilter, 'all'> | null;
};

export type UserStatusFilter = 'all' | 'verified' | 'unverified';

export type UsersIndexProps = Omit<
    GenericIndexProps<UserRow, UserFilters>,
    'records'
> & {
    users: GenericIndexProps<UserRow, UserFilters>['records'];
};

export type CreateUserForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    search: string;
    status: UserStatusFilter;
    page: number;
    per_page: number;
};

export type UpdateUserForm = {
    name: string;
    email: string;
    search: string;
    status: UserStatusFilter;
    page: number;
    per_page: number;
};

export type DeleteUserForm = {
    search: string;
    status: UserStatusFilter;
    page: number;
    per_page: number;
};
