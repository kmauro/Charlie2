<?php

namespace App\Domains\Users\Requests;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FilterUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(RoleCode::class)],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => Str::of((string) $this->input('search'))->squish()->toString(),
            ]);
        }
    }
}
