import type { Paginator } from '@/shared/types/inertia';

export interface PatientOption {
    value: string;
    label: string;
}

export interface PatientContact {
    id?: number;
    full_name: string;
    relationship: string;
    phone: string | null;
    email: string | null;
    is_emergency_contact: boolean;
    is_legal_representative: boolean;
    is_financially_responsible: boolean;
}

export interface PatientListItem {
    id: number;
    display_name: string;
    first_name: string;
    last_name: string;
    document_number: string;
    birth_date: string;
    age: number;
    phone: string | null;
    coverage_name: string;
    member_number: string | null;
    status: PatientOption;
    archived_at: string | null;
    updated_at: string;
    contacts_count: number;
    permissions: PatientPermissions;
}

export interface Patient extends PatientListItem {
    lock_version: number;
    registered_sex: PatientOption;
    gender: string | null;
    email: string | null;
    address: string | null;
    contacts: PatientContact[];
}

export interface PatientContactFormData {
    full_name: string;
    relationship: string;
    phone: string;
    email: string;
    is_emergency_contact: boolean;
    is_legal_representative: boolean;
    is_financially_responsible: boolean;
}

export interface PatientFormData {
    lock_version?: number;
    first_name: string;
    last_name: string;
    document_number: string;
    birth_date: string;
    registered_sex: string;
    gender: string;
    phone: string;
    email: string;
    address: string;
    coverage_name: string;
    member_number: string;
    status: string;
    contacts: PatientContactFormData[];
}

export interface PatientFilters {
    search?: string;
    status?: string;
}

export interface PatientSummaries {
    total: number;
    active: number;
    inpatient: number;
    archived: number;
    deceased: number;
    blocked: number;
}

export interface PatientPermissions {
    create?: boolean;
    view?: boolean;
    update?: boolean;
    archive?: boolean;
    restore?: boolean;
    manage_status?: boolean;
    access_external_clinical_record?: boolean;
}

export type PatientsPaginator = Paginator<PatientListItem>;
