export type DashboardTone = 'blue' | 'emerald' | 'violet' | 'amber';

export interface DashboardMetric {
    label: string;
    value: string;
    detail: string;
    icon: string;
    tone: DashboardTone;
}

export interface UpcomingAppointment {
    time: string;
    patient: string;
    professional: string;
    specialty: string;
    status: string;
    tone: DashboardTone;
}

export interface AppointmentContext {
    location: string;
    period: string;
}

export interface OperationalStatus {
    title: string;
    detail: string;
    icon: string;
    tone: DashboardTone;
}

export interface DashboardProps {
    metrics: DashboardMetric[];
    upcomingAppointments: UpcomingAppointment[];
    appointmentContext: AppointmentContext;
    operationalStatuses: OperationalStatus[];
}
