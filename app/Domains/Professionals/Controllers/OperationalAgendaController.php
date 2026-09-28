<?php

namespace App\Domains\Professionals\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\LocationHoliday;
use App\Domains\Professionals\Models\ProfessionalLeave;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Models\ProfessionalScheduleBreak;
use App\Domains\Professionals\Models\ProfessionalScheduleException;
use App\Domains\Professionals\Requests\StoreConsultingRoomRequest;
use App\Domains\Professionals\Requests\StoreLocationHolidayRequest;
use App\Domains\Professionals\Requests\StoreProfessionalLeaveRequest;
use App\Domains\Professionals\Requests\StoreProfessionalScheduleBreakRequest;
use App\Domains\Professionals\Requests\StoreProfessionalScheduleExceptionRequest;
use App\Domains\Professionals\Requests\UpdateProfessionalScheduleRoomRequest;
use App\Domains\Professionals\Services\OperationalAgendaService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OperationalAgendaController extends Controller
{
    public function __construct(private OperationalAgendaService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ConsultingRoom::class);
        $actor = $this->actor($request);

        return Inertia::render('professionals/views/OperationalAgenda', [
            ...$this->service->pageData($actor),
            'permissions' => ['manage' => $actor->can('manage', ConsultingRoom::class)],
        ]);
    }

    public function storeRoom(StoreConsultingRoomRequest $request): RedirectResponse
    {
        $this->service->createRoom($request->validated(), $this->actor($request), $this->context($request));

        return $this->success('El consultorio fue creado.');
    }

    public function destroyRoom(Request $request, ConsultingRoom $room): RedirectResponse
    {
        Gate::authorize('manage', ConsultingRoom::class);
        $this->service->deactivateRoom($room, $this->actor($request), $this->context($request));

        return $this->success('El consultorio fue desactivado.');
    }

    public function storeHoliday(StoreLocationHolidayRequest $request): RedirectResponse
    {
        $this->service->createHoliday($request->validated(), $this->actor($request), $this->context($request));

        return $this->success('El feriado fue agregado.');
    }

    public function destroyHoliday(Request $request, LocationHoliday $holiday): RedirectResponse
    {
        Gate::authorize('manage', ConsultingRoom::class);
        $this->service->delete($holiday, 'agenda.holiday.deleted', $this->actor($request), $this->context($request), (int) $holiday->location_id);

        return $this->success('El feriado fue eliminado.');
    }

    public function storeLeave(StoreProfessionalLeaveRequest $request): RedirectResponse
    {
        $this->service->createLeave($request->validated(), $this->actor($request), $this->context($request));

        return $this->success('La licencia fue registrada.');
    }

    public function destroyLeave(Request $request, ProfessionalLeave $leave): RedirectResponse
    {
        Gate::authorize('manage', ConsultingRoom::class);
        $this->service->delete($leave, 'agenda.leave.deleted', $this->actor($request), $this->context($request), $leave->location_id === null ? null : (int) $leave->location_id);

        return $this->success('La licencia fue eliminada.');
    }

    public function storeException(StoreProfessionalScheduleExceptionRequest $request): RedirectResponse
    {
        $this->service->createException($request->validated(), $this->actor($request), $this->context($request));

        return $this->success('La excepción fue registrada.');
    }

    public function destroyException(Request $request, ProfessionalScheduleException $exception): RedirectResponse
    {
        Gate::authorize('manage', ConsultingRoom::class);
        $this->service->delete($exception, 'agenda.exception.deleted', $this->actor($request), $this->context($request), (int) $exception->location_id);

        return $this->success('La excepción fue eliminada.');
    }

    public function storeBreak(StoreProfessionalScheduleBreakRequest $request, ProfessionalSchedule $schedule): RedirectResponse
    {
        $this->service->createBreak($schedule, $request->validated(), $this->actor($request), $this->context($request));

        return $this->success('El descanso fue agregado.');
    }

    public function destroyBreak(Request $request, ProfessionalScheduleBreak $break): RedirectResponse
    {
        Gate::authorize('manage', ConsultingRoom::class);
        $break->loadMissing('schedule');
        $this->service->delete($break, 'agenda.break.deleted', $this->actor($request), $this->context($request), (int) $break->schedule->location_id);

        return $this->success('El descanso fue eliminado.');
    }

    public function updateScheduleRoom(UpdateProfessionalScheduleRoomRequest $request, ProfessionalSchedule $schedule): RedirectResponse
    {
        $roomId = $request->validated('consulting_room_id');
        $this->service->assignRoom($schedule, $roomId === null ? null : (int) $roomId, $this->actor($request), $this->context($request));

        return $this->success('La asignación de consultorio fue actualizada.');
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('success', $message);

        return back();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function context(Request $request): AuditContext
    {
        return AuditContext::fromRequest($request);
    }
}
