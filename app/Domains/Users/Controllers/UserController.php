<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use App\Domains\Users\Repositories\Contracts\RoleRepository;
use App\Domains\Users\Repositories\Contracts\UserRepository;
use App\Domains\Users\Requests\FilterUsersRequest;
use App\Domains\Users\Requests\StoreUserRequest;
use App\Domains\Users\Requests\UpdateUserRequest;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private UserService $service,
    ) {}

    public function index(FilterUsersRequest $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->safe()->only(['search', 'role', 'status']);
        $users = $this->users->paginate($filters);

        return Inertia::render('users/views/Index', [
            'users' => $users->through(fn (User $user): array => $this->userData($user)),
            'filters' => $filters,
            'roles' => $this->roleOptions(includeProfessional: true),
            'summaries' => $this->users->summaries(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/views/Create', [
            'roles' => $this->roleOptions(),
            'permissionOptions' => UserPermission::options(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $invitation = $this->service->invite($request->validated());

        Inertia::flash([
            'success' => 'La invitación fue creada correctamente.',
            'invitationUrl' => $invitation->invitationUrl,
        ]);

        return redirect()->route('users.index');
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        $user->loadMissing('role');

        return Inertia::render('users/views/Edit', [
            'user' => $this->userData($user),
            'roles' => $this->roleOptions(
                fixedRole: $user->role?->code === RoleCode::Professional
                    ? RoleCode::Professional
                    : null,
            ),
            'permissionOptions' => UserPermission::options(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->service->update($user, $request->validated());

        Inertia::flash('success', 'El usuario fue actualizado correctamente.');

        return redirect()->route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $this->service->suspend($user);
        Inertia::flash('success', 'La cuenta fue suspendida.');

        return back();
    }

    public function restore(User $user): RedirectResponse
    {
        Gate::authorize('restore', $user);
        $this->service->restore($user);
        Inertia::flash('success', 'La cuenta fue recuperada.');

        return back();
    }

    public function reinvite(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $invitation = $this->service->reinvite($user);

        Inertia::flash([
            'success' => 'Se generó una nueva invitación.',
            'invitationUrl' => $invitation->invitationUrl,
        ]);

        return back();
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function roleOptions(
        ?RoleCode $fixedRole = null,
        bool $includeProfessional = false,
    ): array
    {
        return $this->roles
            ->all()
            ->when(
                $fixedRole !== null,
                fn ($roles) => $roles->filter(
                    fn (Role $role): bool => $role->code === $fixedRole,
                ),
            )
            ->when(
                $fixedRole === null && ! $includeProfessional,
                fn ($roles) => $roles->reject(
                    fn (Role $role): bool => $role->code === RoleCode::Professional,
                ),
            )
            ->values()
            ->map(fn (Role $role): array => [
                'id' => $role->getKey(),
                'code' => $role->code->value,
                'name' => $role->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role === null ? null : [
                'code' => $user->role->code->value,
                'name' => $user->role->name,
            ],
            'role_id' => $user->role_id,
            'status' => [
                'value' => $user->status->value,
                'label' => $user->status->label(),
            ],
            'permissions' => $user->permissions ?? [],
            'last_access_at' => $user->last_login_at?->toIso8601String(),
            'invitation_expires_at' => $user->invitation_expires_at?->toIso8601String(),
            'invitation_accepted_at' => $user->invitation_accepted_at?->toIso8601String(),
        ];
    }
}
