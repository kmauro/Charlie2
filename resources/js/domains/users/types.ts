import type { Paginator, RoleReference } from '@/shared/types/inertia';

export interface RoleOption extends RoleReference {
    id: number;
}

export interface PermissionOption {
    value: string;
    label: string;
    description?: string;
    allowed_roles: string[];
}

export interface ScopeOption {
    value: number;
    label: string;
    legal_entity_id?: number;
}

export interface UserStatus {
    value: string;
    label: string;
}

export interface UserListItem {
    id: number;
    name: string;
    email: string;
    role: RoleReference | null;
    status: UserStatus;
    permissions: string[];
    legal_entity_ids: number[];
    location_ids: number[];
    last_access_at: string | null;
    invitation_expires_at: string | null;
    invitation_accepted_at: string | null;
}

export interface EditableUser extends UserListItem {
    role_id?: number | null;
}

export interface UserFilters {
    search?: string;
    role?: string;
    status?: string;
}

export interface UserSummaries {
    total: number;
    active: number;
    pending: number;
    suspended: number;
    custom_permissions: number;
}

export type UsersPaginator = Paginator<UserListItem>;
