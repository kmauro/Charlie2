import type { Paginator } from '@/shared/types/inertia';

export interface OrganizationOption {
    value: string;
    label: string;
}

export interface LegalEntityOption {
    id: number;
    label: string;
    cuit: string;
}

export interface ResourcePermissions {
    update?: boolean;
    archive?: boolean;
    restore?: boolean;
}

export interface OrganizationPermissions {
    create?: boolean;
    manage_legal_entities?: boolean;
}

export interface Location {
    id: number;
    legal_entity_id: number;
    legal_entity: {
        id: number;
        business_name: string;
        cuit: string;
    };
    name: string;
    code: string;
    address: string;
    neighborhood: string | null;
    postal_code: string | null;
    phone: string | null;
    email: string | null;
    opening_date: string | null;
    opening_time: string;
    closing_time: string;
    timezone: string;
    services: string[];
    service_labels: string[];
    status: OrganizationOption;
    archived_at: string | null;
    updated_at: string;
    permissions: ResourcePermissions;
}

export interface LegalEntity {
    id: number;
    business_name: string;
    cuit: string;
    formatted_cuit: string;
    tax_condition: string;
    fiscal_address: string;
    fiscal_point_of_sale: number | null;
    arca_environment: string | null;
    status: OrganizationOption;
    archived_at: string | null;
    updated_at: string;
    locations_count: number;
    active_locations_count: number;
    unarchived_locations_count: number;
    permissions: ResourcePermissions;
}

export interface LocationFormData {
    legal_entity_id: number | '';
    name: string;
    code: string;
    address: string;
    neighborhood: string;
    postal_code: string;
    phone: string;
    email: string;
    opening_date: string;
    opening_time: string;
    closing_time: string;
    timezone: string;
    services: string[];
    status: string;
}

export interface LegalEntityFormData {
    business_name: string;
    cuit: string;
    tax_condition: string;
    fiscal_address: string;
    fiscal_point_of_sale: number | '';
    arca_environment: string;
    arca_certificate: string;
    arca_private_key: string;
    status: string;
}

export interface LocationFilters {
    search?: string;
    status?: string;
    legal_entity_id?: number | string;
}

export interface LegalEntityFilters {
    search?: string;
    status?: string;
}

export interface OrganizationSummaries {
    locations_total: number;
    locations_active: number;
    locations_preparation: number;
    locations_archived: number;
    legal_entities_total: number;
    legal_entities_active: number;
}

export type LocationsPaginator = Paginator<Location>;
export type LegalEntitiesPaginator = Paginator<LegalEntity>;
