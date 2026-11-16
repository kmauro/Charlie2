<?php

namespace App\Domains\Operations\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Operations\Models\EmergencyVisit;
use App\Domains\Operations\Requests\ChangeOperationalStatusRequest;
use App\Domains\Operations\Requests\StoreEmergencyVisitRequest;
use App\Domains\Operations\Services\OperationsService;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyVisitController extends Controller
{
    public function __construct(private OperationsService $operations, private UserScopeService $scope) {}
    public function index(Request $request): Response { Gate::authorize('viewAny', EmergencyVisit::class); $actor = $this->actor($request); return Inertia::render('operations/views/EmergencyQueue', ['visits' => $this->operations->emergencyQueue($this->scope->locationIdsFor($actor))->map(fn (EmergencyVisit $visit) => ['id' => $visit->id, 'patient' => trim($visit->patient->last_name.', '.$visit->patient->first_name), 'document_number' => $visit->patient->document_number, 'location' => $visit->location->name, 'status' => $visit->status->value, 'priority' => $visit->priority, 'arrived_at' => $visit->arrived_at->toISOString(), 'lock_version' => $visit->lock_version]), 'permissions' => ['create' => $actor->can('create', EmergencyVisit::class)]]); }
    public function store(StoreEmergencyVisitRequest $request): RedirectResponse { $this->operations->createEmergencyVisit($request->validated(), $this->actor($request), $this->context($request)); return back()->with('success', 'Ingreso de guardia registrado.'); }
    public function start(ChangeOperationalStatusRequest $request, EmergencyVisit $emergencyVisit): RedirectResponse { Gate::authorize('update', $emergencyVisit); $this->operations->startEmergencyVisit($emergencyVisit, (int) $request->validated('lock_version'), $this->actor($request), $this->context($request)); return back()->with('success', 'Atención administrativa iniciada.'); }
    public function close(ChangeOperationalStatusRequest $request, EmergencyVisit $emergencyVisit): RedirectResponse { Gate::authorize('update', $emergencyVisit); $this->operations->closeEmergencyVisit($emergencyVisit, $request->validated(), $this->actor($request), $this->context($request)); return back()->with('success', 'Ingreso de guardia cerrado.'); }
    public function cancel(ChangeOperationalStatusRequest $request, EmergencyVisit $emergencyVisit): RedirectResponse { Gate::authorize('update', $emergencyVisit); $this->operations->cancelEmergencyVisit($emergencyVisit, $request->validated(), $this->actor($request), $this->context($request)); return back()->with('success', 'Ingreso de guardia anulado.'); }
    private function actor(Request $request): User { $user = $request->user(); abort_unless($user instanceof User, 401); return $user; }
    private function context(Request $request): AuditContext { return new AuditContext(ipAddress: $request->ip(), userAgent: $request->userAgent(), correlationId: $request->header('X-Request-ID')); }
}
