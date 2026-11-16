<?php

namespace App\Domains\Operations\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Operations\Models\Admission;
use App\Domains\Operations\Models\BedAssignment;
use App\Domains\Operations\Requests\ChangeOperationalStatusRequest;
use App\Domains\Operations\Requests\StoreAdmissionRequest;
use App\Domains\Operations\Requests\TransferAdmissionRequest;
use App\Domains\Operations\Services\OperationsService;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    public function __construct(private OperationsService $operations, private UserScopeService $scope) {}
    public function index(Request $request): Response { Gate::authorize('viewAny', Admission::class); $actor = $this->actor($request); return Inertia::render('operations/views/InpatientCensus', ['admissions' => $this->operations->census($this->scope->locationIdsFor($actor))->map(function (Admission $admission): array { $assignment = $admission->assignments->first(); return ['id' => $admission->id, 'patient' => trim($admission->patient->last_name.', '.$admission->patient->first_name), 'document_number' => $admission->patient->document_number, 'location' => $admission->location->name, 'admitted_at' => $admission->admitted_at->toISOString(), 'lock_version' => $admission->lock_version, 'bed_assignment_id' => $assignment?->id, 'bed' => $assignment === null ? null : $assignment->bed->room->ward->name.' / '.$assignment->bed->room->code.' / '.$assignment->bed->code, 'received_at' => $assignment?->received_at?->toISOString()]; }), 'permissions' => ['admit' => $actor->can('create', Admission::class)]]); }
    public function store(StoreAdmissionRequest $request): RedirectResponse { $this->operations->admit($request->validated(), $this->actor($request), $this->context($request)); return back()->with('success', 'Internación registrada.'); }
    public function transfer(TransferAdmissionRequest $request, Admission $admission): RedirectResponse { $this->operations->transfer($admission, $request->validated(), $this->actor($request), $this->context($request)); return back()->with('success', 'Traslado registrado.'); }
    public function discharge(ChangeOperationalStatusRequest $request, Admission $admission): RedirectResponse { Gate::authorize('update', $admission); $this->operations->discharge($admission, (int) $request->validated('lock_version'), $this->actor($request), $this->context($request)); return back()->with('success', 'Egreso administrativo registrado.'); }
    public function confirmReception(Request $request, BedAssignment $bedAssignment): RedirectResponse { Gate::authorize('confirmReception', $bedAssignment->admission); $this->operations->confirmReception($bedAssignment, $this->actor($request), $this->context($request)); return back()->with('success', 'Recepción confirmada.'); }
    private function actor(Request $request): User { $user = $request->user(); abort_unless($user instanceof User, 401); return $user; }
    private function context(Request $request): AuditContext { return new AuditContext(ipAddress: $request->ip(), userAgent: $request->userAgent(), correlationId: $request->header('X-Request-ID')); }
}
