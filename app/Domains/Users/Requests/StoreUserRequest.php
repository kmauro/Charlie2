<?php

namespace App\Domains\Users\Requests;

use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'expires_in_hours' => ['required', 'integer', Rule::in([24, 48, 72, 168])],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(UserPermission::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
            'email' => Str::of((string) $this->input('email'))->trim()->lower()->toString(),
            'permissions' => $this->input('permissions', []),
        ]);
    }
}
