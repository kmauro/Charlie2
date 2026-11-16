<?php

namespace App\Domains\Operations\Services;

use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Operations\Enums\AdmissionStatus;
use App\Domains\Operations\Enums\EmergencyVisitStatus;
use App\Domains\Operations\Models\Admission;
use App\Domains\Operations\Models\Bed;
use App\Domains\Operations\Models\BedAssignment;
use App\Domains\Operations\Models\EmergencyVisit;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationsService
{
    public function __construct(private AuditService $audit, private UserScopeService $scope) {}

    public function createEmergencyVisit(array $data, User $actor, AuditContext $context): EmergencyVisit
    {
        $this->ensureLocation($actor, (int) $data['location_id']);
        return DB::transaction(function () use ($data, $actor, $context): EmergencyVisit {
            $patient = Patient::query()->lockForUpdate()->findOrFail($data['patient_id']);
            $appointmentId = $data['appointment_id'] ?? null;
            if ($appointmentId !== null) {
                $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointmentId);
                if ($appointment->patient_id !== $patient->getKey() || $appointment->location_id !== (int) $data['location_id'] || $appointment->type !== AppointmentType::Emergency) {
                    throw ValidationException::withMessages(['appointment_id' => 'El turno de guardia no corresponde al paciente o sede seleccionados.']);
                }
                if (EmergencyVisit::query()->where('appointment_id', $appointment->getKey())->exists()) {
                    throw ValidationException::withMessages(['appointment_id' => 'El turno ya tiene un ingreso de guardia asociado.']);
                }
            }
            $visit = EmergencyVisit::query()->create([
                ...$data,
                'arrived_at' => $data['arrived_at'] ?? now(),
                'created_by' => $actor->getKey(),
                'lock_version' => 1,
            ]);
            $this->audit->record('emergency_visit.created', $visit, actor: $actor, context: $context);
            return $visit;
        });
    }

    public function startEmergencyVisit(EmergencyVisit $visit, int $version, User $actor, AuditContext $context): EmergencyVisit
    {
        return $this->changeVisit($visit, $version, $actor, $context, EmergencyVisitStatus::InProgress, 'emergency_visit.started');
    }

    public function closeEmergencyVisit(EmergencyVisit $visit, array $data, User $actor, AuditContext $context): EmergencyVisit
    {
        return DB::transaction(function () use ($visit, $data, $actor, $context): EmergencyVisit {
            $locked = EmergencyVisit::query()->lockForUpdate()->findOrFail($visit->getKey());
            $this->ensureVisitVersion($locked, (int) $data['lock_version']);
            $this->ensureLocation($actor, $locked->location_id);
            if (! in_array($locked->status, [EmergencyVisitStatus::Waiting, EmergencyVisitStatus::InProgress], true)) {
                throw ValidationException::withMessages(['status' => 'El ingreso ya está finalizado.']);
            }
            $locked->update(['status' => EmergencyVisitStatus::Closed, 'closed_at' => now(), 'closed_by' => $actor->getKey(), 'outcome' => $data['outcome'], 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record('emergency_visit.closed', $locked, actor: $actor, context: $context);
            return $locked;
        });
    }

    public function cancelEmergencyVisit(EmergencyVisit $visit, array $data, User $actor, AuditContext $context): EmergencyVisit
    {
        return DB::transaction(function () use ($visit, $data, $actor, $context): EmergencyVisit {
            $locked = EmergencyVisit::query()->lockForUpdate()->findOrFail($visit->getKey());
            $this->ensureVisitVersion($locked, (int) $data['lock_version']);
            $this->ensureLocation($actor, $locked->location_id);
            if (! in_array($locked->status, [EmergencyVisitStatus::Waiting, EmergencyVisitStatus::InProgress], true)) {
                throw ValidationException::withMessages(['status' => 'El ingreso ya está finalizado.']);
            }
            $locked->update(['status' => EmergencyVisitStatus::Cancelled, 'closed_at' => now(), 'closed_by' => $actor->getKey(), 'cancellation_reason' => $data['cancellation_reason'], 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record('emergency_visit.cancelled', $locked, actor: $actor, context: $context);
            return $locked;
        });
    }

    public function admit(array $data, User $actor, AuditContext $context): Admission
    {
        $this->ensureLocation($actor, (int) $data['location_id']);
        return DB::transaction(function () use ($data, $actor, $context): Admission {
            $patient = Patient::query()->lockForUpdate()->findOrFail($data['patient_id']);
            if (Admission::query()->where('patient_id', $patient->getKey())->where('status', AdmissionStatus::Active)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['patient_id' => 'El paciente ya tiene una internación activa.']);
            }
            $bed = $this->availableBed((int) $data['bed_id'], (int) $data['location_id']);
            $visit = isset($data['emergency_visit_id']) ? EmergencyVisit::query()->lockForUpdate()->findOrFail($data['emergency_visit_id']) : null;
            if ($visit !== null && ($visit->patient_id !== $patient->getKey() || $visit->location_id !== (int) $data['location_id'] || $visit->status === EmergencyVisitStatus::Cancelled)) {
                throw ValidationException::withMessages(['emergency_visit_id' => 'El ingreso de guardia no puede derivarse a esta internación.']);
            }
            $admission = Admission::query()->create(['patient_id' => $patient->getKey(), 'location_id' => $data['location_id'], 'emergency_visit_id' => $visit?->getKey(), 'admitted_at' => now(), 'admitted_by' => $actor->getKey(), 'lock_version' => 1]);
            BedAssignment::query()->create(['admission_id' => $admission->getKey(), 'bed_id' => $bed->getKey(), 'started_at' => now(), 'assigned_by' => $actor->getKey()]);
            $patient->update(['status' => PatientStatus::Inpatient]);
            if ($visit !== null && $visit->status !== EmergencyVisitStatus::Closed) {
                $visit->update(['status' => EmergencyVisitStatus::Closed, 'outcome' => 'admission', 'closed_at' => now(), 'closed_by' => $actor->getKey(), 'lock_version' => $visit->lock_version + 1]);
            }
            $this->audit->record('admission.created', $admission, actor: $actor, context: $context);
            return $admission;
        });
    }

    public function transfer(Admission $admission, array $data, User $actor, AuditContext $context): Admission
    {
        return DB::transaction(function () use ($admission, $data, $actor, $context): Admission {
            $locked = Admission::query()->lockForUpdate()->findOrFail($admission->getKey());
            $this->ensureAdmissionVersion($locked, (int) $data['lock_version']);
            $this->ensureLocation($actor, $locked->location_id);
            if ($locked->status !== AdmissionStatus::Active) throw ValidationException::withMessages(['status' => 'La internación ya finalizó.']);
            $current = BedAssignment::query()->where('admission_id', $locked->getKey())->whereNull('ended_at')->lockForUpdate()->sole();
            $next = $this->availableBed((int) $data['bed_id'], $locked->location_id);
            if ($current->bed_id === $next->getKey()) throw ValidationException::withMessages(['bed_id' => 'El paciente ya ocupa esa cama.']);
            $current->update(['ended_at' => now(), 'ended_by' => $actor->getKey()]);
            BedAssignment::query()->create(['admission_id' => $locked->getKey(), 'bed_id' => $next->getKey(), 'started_at' => now(), 'assigned_by' => $actor->getKey()]);
            $locked->update(['lock_version' => $locked->lock_version + 1]);
            $this->audit->record('admission.transferred', $locked, actor: $actor, context: $context);
            return $locked;
        });
    }

    public function discharge(Admission $admission, int $version, User $actor, AuditContext $context): Admission
    {
        return DB::transaction(function () use ($admission, $version, $actor, $context): Admission {
            $locked = Admission::query()->lockForUpdate()->findOrFail($admission->getKey());
            $this->ensureAdmissionVersion($locked, $version); $this->ensureLocation($actor, $locked->location_id);
            if ($locked->status !== AdmissionStatus::Active) throw ValidationException::withMessages(['status' => 'La internación ya finalizó.']);
            $patient = Patient::query()->lockForUpdate()->findOrFail($locked->patient_id);
            BedAssignment::query()->where('admission_id', $locked->getKey())->whereNull('ended_at')->lockForUpdate()->sole()->update(['ended_at' => now(), 'ended_by' => $actor->getKey()]);
            $locked->update(['status' => AdmissionStatus::Discharged, 'discharged_at' => now(), 'discharged_by' => $actor->getKey(), 'lock_version' => $locked->lock_version + 1]);
            $patient->update(['status' => PatientStatus::Active]);
            $this->audit->record('admission.discharged', $locked, actor: $actor, context: $context);
            return $locked;
        });
    }

    public function confirmReception(BedAssignment $assignment, User $actor, AuditContext $context): BedAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $context): BedAssignment {
            $locked = BedAssignment::query()->with('admission')->lockForUpdate()->findOrFail($assignment->getKey());
            $this->ensureLocation($actor, $locked->admission->location_id);
            $locked->update(['received_at' => now(), 'received_by' => $actor->getKey()]);
            $this->audit->record('bed_assignment.receipt_confirmed', $locked, actor: $actor, context: $context);
            return $locked;
        });
    }

    /** @return Collection<int, EmergencyVisit> */
    public function emergencyQueue(?array $locationIds): Collection { return EmergencyVisit::query()->with(['patient:id,first_name,last_name,document_number', 'location:id,name'])->whereIn('status', [EmergencyVisitStatus::Waiting, EmergencyVisitStatus::InProgress])->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))->oldest('arrived_at')->get(); }
    /** @return Collection<int, Admission> */
    public function census(?array $locationIds): Collection { return Admission::query()->with(['patient:id,first_name,last_name,document_number', 'location:id,name', 'assignments' => fn ($q) => $q->whereNull('ended_at')->with('bed.room.ward')])->where('status', AdmissionStatus::Active)->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))->oldest('admitted_at')->get(); }

    private function availableBed(int $bedId, int $locationId): Bed
    {
        $bed = Bed::query()->with('room.ward')->lockForUpdate()->findOrFail($bedId);
        if (! $bed->is_active || ! $bed->room->is_active || ! $bed->room->ward->is_active || $bed->room->ward->location_id !== $locationId) throw ValidationException::withMessages(['bed_id' => 'La cama no está habilitada para esta sede.']);
        if (BedAssignment::query()->where('bed_id', $bed->getKey())->whereNull('ended_at')->lockForUpdate()->exists()) throw ValidationException::withMessages(['bed_id' => 'La cama acaba de ser ocupada. Recargá el censo.']);
        return $bed;
    }
    private function changeVisit(EmergencyVisit $visit, int $version, User $actor, AuditContext $context, EmergencyVisitStatus $status, string $event): EmergencyVisit { return DB::transaction(function () use ($visit, $version, $actor, $context, $status, $event): EmergencyVisit { $locked = EmergencyVisit::query()->lockForUpdate()->findOrFail($visit->getKey()); $this->ensureVisitVersion($locked, $version); $this->ensureLocation($actor, $locked->location_id); if ($locked->status !== EmergencyVisitStatus::Waiting) throw ValidationException::withMessages(['status' => 'Solo se puede iniciar un ingreso en espera.']); $locked->update(['status' => $status, 'started_at' => now(), 'started_by' => $actor->getKey(), 'lock_version' => $locked->lock_version + 1]); $this->audit->record($event, $locked, actor: $actor, context: $context); return $locked; }); }
    private function ensureVisitVersion(EmergencyVisit $visit, int $version): void { if ($visit->lock_version !== $version) throw ValidationException::withMessages(['lock_version' => 'El ingreso fue actualizado por otra persona. Recargá la bandeja.']); }
    private function ensureAdmissionVersion(Admission $admission, int $version): void { if ($admission->lock_version !== $version) throw ValidationException::withMessages(['lock_version' => 'La internación fue actualizada por otra persona. Recargá el censo.']); }
    private function ensureLocation(User $actor, int $locationId): void { if (! $this->scope->canAccessLocation($actor, $locationId)) throw ValidationException::withMessages(['location_id' => 'La sede seleccionada está fuera de tu alcance.']); }
}
