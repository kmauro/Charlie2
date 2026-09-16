<?php

namespace App\Domains\Users\Requests;

use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(UserPermission::class)],
            'legal_entity_ids' => ['array'],
            'legal_entity_ids.*' => ['integer', 'distinct', Rule::exists(LegalEntity::class, 'id')],
            'location_ids' => ['array'],
            'location_ids.*' => ['integer', 'distinct', Rule::exists(Location::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
            'email' => Str::of((string) $this->input('email'))->trim()->lower()->toString(),
            'permissions' => $this->input('permissions', []),
            'legal_entity_ids' => $this->input('legal_entity_ids', []),
            'location_ids' => $this->input('location_ids', []),
        ]);
    }
}
