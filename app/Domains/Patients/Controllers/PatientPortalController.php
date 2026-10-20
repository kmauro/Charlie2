<?php

namespace App\Domains\Patients\Controllers;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Audits\Data\AuditContext;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Requests\UpdatePatientPortalProfileRequest;
use App\Domains\Patients\Services\PatientService;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientPortalController extends Controller
{
    public function __construct(private PatientService $service) {}

    public function show(Request $request): Response
    {
        $actor = $this->patientUser($request);
        $patient = $this->patientFor($actor);
        $preference = $patient->notificationPreference()->first();

        return Inertia::render('patients/views/Portal', [
            'patient' => [
                'display_name' => trim($patient->first_name.' '.$patient->last_name),
                'document_number' => $patient->document_number,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'address' => $patient->address,
                'coverage_name' => $patient->coverage_name,
                'member_number' => $patient->member_number,
                'notification_preferences' => [
                    'appointment_reminders_by_email' => $preference?->appointment_reminders_by_email ?? true,
                    'appointment_reminders_by_whatsapp' => $preference?->appointment_reminders_by_whatsapp ?? false,
                    'appointment_reminder_hours' => $preference?->appointment_reminder_hours ?? 24,
                ],
            ],
            'upcomingAppointments' => Appointment::query()
                ->whereBelongsTo($patient)
                ->where('starts_at', '>=', today())
                ->whereNotIn('status', ['cancelled', 'absent'])
                ->with(['professional.user:id,name', 'location:id,name'])
                ->oldest('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => (int) $appointment->getKey(),
                    'starts_at' => $appointment->starts_at->toISOString(),
                    'professional' => $appointment->professional->user->name,
                    'location' => $appointment->location->name,
                    'status' => $appointment->status->label(),
                ]),
        ]);
    }

    public function update(UpdatePatientPortalProfileRequest $request): RedirectResponse
    {
        $actor = $this->patientUser($request);
        $this->service->updatePortalProfile(
            $this->patientFor($actor),
            $request->validated(),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'Tus datos de contacto fueron actualizados.');

        return back();
    }

    private function patientUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole(RoleCode::Patient), 403);

        return $user;
    }

    private function patientFor(User $user): Patient
    {
        $patient = $user->patient()->firstOrFail();
        abort_unless($patient->canAccessPortal(), 403);

        return $patient;
    }

    private function auditContext(Request $request): AuditContext
    {
        $correlationId = $request->header('X-Request-ID');

        return new AuditContext(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }
}
