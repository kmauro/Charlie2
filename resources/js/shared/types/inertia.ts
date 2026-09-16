export interface RoleReference {
    code: string;
    name: string;
}

export interface AuthenticatedUser {
    id: number;
    name: string;
    email: string;
    role?: RoleReference | string | null;
}

export interface AppFlashData {
    success?: string;
    error?: string;
    invitationUrl?: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}
