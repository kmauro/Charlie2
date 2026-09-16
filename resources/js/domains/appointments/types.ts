import type { Paginator } from '@/shared/types/inertia';

export interface AppointmentOption {
    value: string;
    label: string;
}

export interface AppointmentLocationOption {
    value: number;
    label: string;
    code?: string;
}

export interface AppointmentPatientOption {
    value: number;
    label: string;
    document_number?: string;
}

export interface ProfessionalScheduleOption {
    location_id?: number | null;
    weekday: number;
    weekday_label?: string;
    start_time: string;
    end_time: string;
    location_name: string;
}

export interface AppointmentProfessionalOption {
    value: number;
    label: string;
    specialty: string;
    duration_minutes: number;
    schedules: ProfessionalScheduleOption[];
}

export interface AppointmentParty {
    id: number;
    name: string;
    document_number?: string;
    specialty?: string;
    code?: string;
}

export interface AppointmentPermissions {
    update: boolean;
    change_status: boolean;
    cancel: boolean;
}

export interface AppointmentUrls {
    edit: string | null;
    status: string | null;
    cancel: string | null;
}

export interface AppointmentListItem {
    id: number;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    patient: AppointmentParty;
    professional: AppointmentParty;
    location: AppointmentParty;
    status: AppointmentOption;
    type: AppointmentOption;
    office: string | null;
    reason: string | null;
    is_overbook: false;
    cancellation: {
        reason: string;
        at: string;
        by_name: string | null;
    } | null;
    lock_version: number;
    available_statuses: AppointmentOption[];
    permissions: AppointmentPermissions;
    urls: AppointmentUrls;
}

export interface AppointmentFormRecord extends AppointmentListItem {
    patient_id?: number;
    professional_id?: number;
    location_id?: number;
}

export interface AppointmentFilters {
    date?: string;
    status?: string;
    type?: string;
    location_id?: number | string;
    professional_id?: number | string;
    search?: string;
}

export interface AppointmentSummaries {
    total: number;
    scheduled: number;
    waiting: number;
    completed: number;
    cancelled: number;
    absent: number;
    today: number;
}

export interface AppointmentFormData {
    patient_id: number | '';
    professional_id: number | '';
    location_id: number | '';
    starts_at: string;
    type: string;
    office: string;
    reason: string;
    lock_version?: number;
}

export type AppointmentsPaginator = Paginator<AppointmentListItem>;
