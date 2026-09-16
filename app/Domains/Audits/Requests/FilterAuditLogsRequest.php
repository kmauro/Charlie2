<?php

namespace App\Domains\Audits\Requests;

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'actor_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'event' => ['nullable', 'string', 'max:160'],
            'outcome' => ['nullable', Rule::in(['success', 'denied', 'failure'])],
            'resource' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ];
    }
}
