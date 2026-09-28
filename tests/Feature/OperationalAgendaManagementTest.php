<?php

use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\LocationHoliday;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalLeave;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Models\ProfessionalScheduleBreak;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function operationalAgendaActor(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', $role->value)->value('id'),
        'permissions' => UserPermission::ManageAppointments->isAllowedFor($role)
            ? [UserPermission::ManageAppointments->value]
            : [],
    ]);
}

/** @return array<string, mixed> */
function operationalAppointmentContext(): array
{
    $startsAt = now()->addWeeks(2)->startOfDay()->addHours(10);
    $location = Location::factory()->create();
    $professional = Professional::factory()->create([
        'status' => ProfessionalStatus::Active,
        'appointment_duration_minutes' => 30,
    ]);
    $schedule = ProfessionalSchedule::factory()->for($professional)->forLocation($location)->create([
        'weekday' => $startsAt->dayOfWeekIso,
        'start_time' => '08:00',
        'end_time' => '18:00',
    ]);

    return [
        'starts_at' => $startsAt,
        'location' => $location,
        'professional' => $professional,
        'schedule' => $schedule,
        'patient' => Patient::factory()->create(),
    ];
}

/** @param array<string, mixed> $context */
function operationalAppointmentPayload(array $context, array $overrides = []): array
{
    return [
        'patient_id' => $context['patient']->getKey(),
        'professional_id' => $context['professional']->getKey(),
        'location_id' => $context['location']->getKey(),
        'starts_at' => $context['starts_at']->format('Y-m-d\TH:i'),
        'type' => AppointmentType::InPerson->value,
        'reason' => 'Control programado',
        ...$overrides,
    ];
}

it('allows appointment operators to view while only administrative roles can manage the agenda', function () {
    foreach ([RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Supervisor] as $role) {
        $actor = operationalAgendaActor($role);
        expect($actor->can('viewAny', ConsultingRoom::class))->toBeTrue()
            ->and($actor->can('manage', ConsultingRoom::class))->toBeTrue();
    }

    foreach ([RoleCode::Reception, RoleCode::Professional] as $role) {
        $actor = $role === RoleCode::Professional
            ? Professional::factory()->create()->user
            : operationalAgendaActor($role);
        expect($actor->can('viewAny', ConsultingRoom::class))->toBeTrue()
            ->and($actor->can('manage', ConsultingRoom::class))->toBeFalse();
    }
});

it('renders only agenda data inside the actor location scope', function () {
    $actor = operationalAgendaActor(RoleCode::Supervisor);
    $visible = Location::factory()->create(['name' => 'Sede Visible']);
    $hidden = Location::factory()->create(['name' => 'Sede Oculta']);
    $actor->locations()->attach($visible);
    ConsultingRoom::factory()->for($visible)->create(['name' => 'Consultorio visible']);
    ConsultingRoom::factory()->for($hidden)->create(['name' => 'Consultorio oculto']);
    $visibleProfessional = Professional::factory()->create();
    $hiddenProfessional = Professional::factory()->create();
    ProfessionalSchedule::factory()->for($visibleProfessional)->forLocation($visible)->create();
    ProfessionalSchedule::factory()->for($hiddenProfessional)->forLocation($hidden)->create();

    $this->actingAs($actor)
        ->get(route('operational_agenda.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/views/OperationalAgenda')
            ->has('locations', 1)
            ->has('professionals', 1, fn (Assert $professional) => $professional
                ->where('value', $visibleProfessional->getKey())
                ->etc())
            ->has('schedules', 1, fn (Assert $schedule) => $schedule
                ->where('professional_id', $visibleProfessional->getKey())
                ->etc())
            ->has('rooms', 1, fn (Assert $room) => $room
                ->where('name', 'Consultorio visible')
                ->etc())
            ->where('permissions.manage', true));
});

it('creates catalog entries and rejects an out of scope location', function () {
    $actor = operationalAgendaActor(RoleCode::Administrator);
    $allowed = Location::factory()->create();
    $denied = Location::factory()->create();
    $actor->locations()->attach($allowed);

    $this->actingAs($actor)->post(route('operational_agenda.rooms.store'), [
        'location_id' => $allowed->getKey(),
        'name' => 'Consultorio cardiológico',
        'code' => 'card-1',
        'equipment' => ['ECG', 'Camilla'],
    ])->assertRedirect();

    $this->assertDatabaseHas('consulting_rooms', [
        'location_id' => $allowed->getKey(),
        'code' => 'CARD-1',
        'is_active' => true,
    ]);

    $this->post(route('operational_agenda.holidays.store'), [
        'location_id' => $denied->getKey(),
        'date' => now()->addMonth()->format('Y-m-d'),
        'name' => 'Cierre local',
    ])->assertSessionHasErrors('location_id');
});

it('blocks appointments during holidays leaves and schedule breaks', function (string $block): void {
    $actor = operationalAgendaActor(RoleCode::Reception);
    $context = operationalAppointmentContext();
    $actor->locations()->attach($context['location']);

    match ($block) {
        'holiday' => LocationHoliday::factory()->for($context['location'])->create([
            'date' => $context['starts_at']->toDateString(),
        ]),
        'leave' => ProfessionalLeave::factory()->for($context['professional'])->create([
            'location_id' => $context['location']->getKey(),
            'starts_on' => $context['starts_at']->toDateString(),
            'ends_on' => $context['starts_at']->toDateString(),
        ]),
        'break' => ProfessionalScheduleBreak::factory()->for($context['schedule'], 'schedule')->create([
            'start_time' => '09:45',
            'end_time' => '10:15',
        ]),
    };

    $this->actingAs($actor)
        ->post(route('appointments.store'), operationalAppointmentPayload($context))
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
})->with(['holiday', 'leave', 'break']);

it('uses date availability exceptions and automatically assigns a free room', function () {
    $actor = operationalAgendaActor(RoleCode::Reception);
    $context = operationalAppointmentContext();
    $actor->locations()->attach($context['location']);
    $room = ConsultingRoom::factory()->for($context['location'])->create();

    $context['professional']->scheduleExceptions()->create([
        'location_id' => $context['location']->getKey(),
        'date' => $context['starts_at']->toDateString(),
        'type' => 'available',
        'start_time' => '09:30',
        'end_time' => '11:00',
        'reason' => 'Horario especial',
    ]);

    $this->actingAs($actor)
        ->post(route('appointments.store'), operationalAppointmentPayload($context))
        ->assertRedirect(route('appointments.index'));

    $appointment = Appointment::query()->sole();
    expect($appointment->consulting_room_id)->toBe($room->getKey())
        ->and($appointment->office)->toBe($room->name);
});

it('blocks a simultaneous use of the same manually selected room', function () {
    $actor = operationalAgendaActor(RoleCode::Reception);
    $context = operationalAppointmentContext();
    $actor->locations()->attach($context['location']);
    $room = ConsultingRoom::factory()->for($context['location'])->create();
    Appointment::factory()->create([
        'patient_id' => Patient::factory(),
        'professional_id' => Professional::factory(),
        'location_id' => $context['location'],
        'consulting_room_id' => $room,
        'office' => $room->name,
        'starts_at' => $context['starts_at'],
        'ends_at' => $context['starts_at']->clone()->addMinutes(30),
    ]);

    $this->actingAs($actor)
        ->post(route('appointments.store'), operationalAppointmentPayload($context, [
            'consulting_room_id' => $room->getKey(),
        ]))
        ->assertSessionHasErrors('consulting_room_id');
});
