<?php

namespace App\Http\Controllers\Domains\Organizations;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationContextController extends Controller
{
    public function __construct(private UserScopeService $scope) {}

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        abort_if($actor->hasRole(RoleCode::Patient), 403);

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);
        abort_unless($this->scope->canAccessLocation($actor, (int) $data['location_id']), 403);
        abort_unless(Location::query()
            ->whereKey($data['location_id'])
            ->where('status', OrganizationStatus::Active->value)
            ->exists(), 403);

        $request->session()->put('selected_organization_location_id', (int) $data['location_id']);

        return back();
    }
}
