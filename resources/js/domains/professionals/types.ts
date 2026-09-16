export interface SelectOption {
    value: string;
    label: string;
}

export interface ProfessionalLocationOption {
    value: number;
    label: string;
    code: string;
}

export interface ProfessionalSchedule {
    id?: number;
    location_id: number | '' | null;
    location_name: string;
    weekday: number;
    weekday_label?: string;
    start_time: string;
    end_time: string;
}

export interface Professional {
    id: number;
    name: string;
    email: string;
    document_number: string;
    phone: string | null;
    specialty: string;
    registration: string;
    registration_type?: string;
    registration_number?: string;
    appointment_duration_minutes: number;
    status: SelectOption;
    office_assignment_type: string | SelectOption;
    fixed_office: string | null;
    schedules: ProfessionalSchedule[];
    invitation_pending: boolean;
}

export interface ProfessionalFormData {
    name: string;
    email: string;
    document_number: string;
    phone: string;
    specialty: string;
    registration_type: string;
    registration_number: string;
    appointment_duration_minutes: number;
    status?: string;
    office_assignment_type: string;
    fixed_office: string;
    schedules: ProfessionalSchedule[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedProfessionals {
    data: Professional[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    to: number | null;
    total: number;
}

export interface ProfessionalFilters {
    search?: string;
    status?: string;
}

export interface ProfessionalSummaries {
    total: number;
    active: number;
    inactive: number;
    on_leave: number;
    invitation_pending?: number;
}
