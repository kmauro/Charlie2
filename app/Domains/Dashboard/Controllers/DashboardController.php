<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard/views/Dashboard', [
            'metrics' => $this->metrics(),
            'upcomingAppointments' => $this->upcomingAppointments(),
            'appointmentContext' => $this->appointmentContext(),
            'operationalStatuses' => $this->operationalStatuses(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: string, detail: string, icon: string, tone: string}>
     */
    private function metrics(): array
    {
        $todayAppointments = $this->todayAppointments();
        $todayCount = $todayAppointments->count();
        $confirmedCount = $todayAppointments
            ->where('status', AppointmentStatus::Confirmed)
            ->count();
        $confirmedPercentage = $todayCount === 0
            ? 0
            : (int) round(($confirmedCount / $todayCount) * 100);
        $activePatients = Patient::query()->active()->count();
        $patientsCreatedThisMonth = Patient::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $activeProfessionals = Professional::query()->active()->count();
        $professionalsAttendingNow = Appointment::query()
            ->where('status', AppointmentStatus::Present->value)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas(
                'professional',
                fn ($query) => $query->where(
                    'status',
                    ProfessionalStatus::Active->value,
                ),
            )
            ->distinct('professional_id')
            ->count('professional_id');
        $activeLocations = $this->activeLocationsCount();

        return [
            [
                'label' => 'Turnos de hoy',
                'value' => (string) $todayCount,
                'detail' => "$confirmedPercentage% confirmados",
                'icon' => 'calendar',
                'tone' => 'blue',
            ],
            [
                'label' => 'Pacientes activos',
                'value' => (string) $activePatients,
                'detail' => "$patientsCreatedThisMonth altas este mes",
                'icon' => 'patients',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Profesionales',
                'value' => (string) $activeProfessionals,
                'detail' => "$professionalsAttendingNow atendiendo ahora",
                'icon' => 'stethoscope',
                'tone' => 'violet',
            ],
            [
                'label' => 'Sedes operativas',
                'value' => (string) $activeLocations,
                'detail' => 'Sin incidencias',
                'icon' => 'building',
                'tone' => 'amber',
            ],
        ];
    }

    /**
     * @return array<int, array{time: string, patient: string, professional: string, specialty: string, status: string, tone: string}>
     */
    private function upcomingAppointments(): array
    {
        return Appointment::query()
            ->with(['patient:id,first_name,last_name', 'professional.user:id,name'])
            ->whereBetween('starts_at', [now(), now()->endOfDay()])
            ->whereIn('status', AppointmentStatus::blockingValues())
            ->orderBy('starts_at')
            ->limit(3)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'time' => $appointment->starts_at->format('H:i'),
                'patient' => trim($appointment->patient->first_name.' '.$appointment->patient->last_name),
                'professional' => $appointment->professional->user->name,
                'specialty' => $appointment->professional->specialty,
                'status' => $appointment->status->label(),
                'tone' => $this->appointmentTone($appointment->status),
            ])
            ->all();
    }

    /**
     * @return array{location: string, period: string}
     */
    private function appointmentContext(): array
    {
        return [
            'location' => 'Todas las sedes',
            'period' => 'Hoy',
        ];
    }

    /**
     * @return array<int, array{title: string, detail: string, icon: string, tone: string}>
     */
    private function operationalStatuses(): array
    {
        $activeLocations = $this->activeLocationsCount();
        $appointmentsToConfirm = Appointment::query()
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('status', AppointmentStatus::Reserved->value)
            ->count();
        $pendingInvitations = User::query()
            ->where('status', UserStatus::PendingInvitation->value)
            ->count();

        return [
            [
                'title' => 'Operación estable',
                'detail' => "$activeLocations sedes disponibles",
                'icon' => 'shield-check',
                'tone' => 'emerald',
            ],
            [
                'title' => "$appointmentsToConfirm confirmaciones",
                'detail' => 'Turnos por validar',
                'icon' => 'clock',
                'tone' => 'amber',
            ],
            [
                'title' => "$pendingInvitations invitaciones",
                'detail' => 'Pendientes de aceptación',
                'icon' => 'users',
                'tone' => 'blue',
            ],
        ];
    }

    /** @return Collection<int, Appointment> */
    private function todayAppointments(): Collection
    {
        return Appointment::query()
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->get(['id', 'status']);
    }

    private function activeLocationsCount(): int
    {
        return Location::query()
            ->where('status', OrganizationStatus::Active->value)
            ->whereNull('archived_at')
            ->count();
    }

    private function appointmentTone(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Confirmed => 'emerald',
            AppointmentStatus::Waiting => 'amber',
            AppointmentStatus::Present => 'blue',
            default => 'violet',
        };
    }
}
