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
    breaks?: Array<{
        start_time: string;
        end_time: string;
        label?: string | null;
    }>;
}

export interface AppointmentRoomOption {
    value: number;
    location_id: number;
    label: string;
    code: string;
    sector?: string | null;
}

export interface AppointmentAgendaConstraints {
    holidays: Array<{ location_id: number; date: string; name: string }>;
    leaves: Array<{
        professional_id: number;
        location_id: number | null;
        starts_on: string;
        ends_on: string;
        reason: string;
    }>;
    exceptions: Array<{
        professional_id: number;
        location_id: number;
        date: string;
        type: 'available' | 'unavailable';
        start_time: string | null;
        end_time: string | null;
        reason: string | null;
    }>;
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
    consulting_room_id: number | null;
    consulting_room: { id: number; name: string; code: string } | null;
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
    consulting_room_id: number | '';
    reason: string;
    lock_version?: number;
}

export type AppointmentsPaginator = Paginator<AppointmentListItem>;
