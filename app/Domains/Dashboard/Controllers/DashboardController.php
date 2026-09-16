<?php

namespace App\Domains\Dashboard\Controllers;

use App\Http\Controllers\Controller;
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
        return [
            [
                'label' => 'Turnos de hoy',
                'value' => '0',
                'detail' => '0% confirmados',
                'icon' => 'calendar',
                'tone' => 'blue',
            ],
            [
                'label' => 'Pacientes activos',
                'value' => '0',
                'detail' => '0 altas este mes',
                'icon' => 'patients',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Profesionales',
                'value' => '0',
                'detail' => '0 atendiendo ahora',
                'icon' => 'stethoscope',
                'tone' => 'violet',
            ],
            [
                'label' => 'Sedes operativas',
                'value' => '0',
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
        return [
            [
                'time' => '08:30',
                'patient' => 'Sofía Benítez',
                'professional' => 'Dra. Laura Méndez',
                'specialty' => 'Cardiología',
                'status' => 'Confirmado',
                'tone' => 'emerald',
            ],
            [
                'time' => '09:00',
                'patient' => 'Martín Rojas',
                'professional' => 'Dr. Tomás Suárez',
                'specialty' => 'Clínica médica',
                'status' => 'En espera',
                'tone' => 'amber',
            ],
            [
                'time' => '09:20',
                'patient' => 'Valentina Paz',
                'professional' => 'Dra. Laura Méndez',
                'specialty' => 'Cardiología',
                'status' => 'Presente',
                'tone' => 'blue',
            ],
        ];
    }

    /**
     * @return array{location: string, period: string}
     */
    private function appointmentContext(): array
    {
        return [
            'location' => 'Sede Palermo',
            'period' => 'Hoy',
        ];
    }

    /**
     * @return array<int, array{title: string, detail: string, icon: string, tone: string}>
     */
    private function operationalStatuses(): array
    {
        return [
            [
                'title' => 'Operación estable',
                'detail' => '6 sedes disponibles',
                'icon' => 'shield-check',
                'tone' => 'emerald',
            ],
            [
                'title' => '7 confirmaciones',
                'detail' => 'Turnos por validar',
                'icon' => 'clock',
                'tone' => 'amber',
            ],
            [
                'title' => '3 invitaciones',
                'detail' => 'Pendientes de aceptación',
                'icon' => 'users',
                'tone' => 'blue',
            ],
        ];
    }
}
