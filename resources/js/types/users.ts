import type { User } from './auth';
import type { GenericPagination } from './pagination';

export type UserRow = Pick<
    User,
    'id' | 'name' | 'email' | 'email_verified_at' | 'created_at' | 'updated_at'
>;

export type PaginatedUsers = GenericPagination<UserRow>;
