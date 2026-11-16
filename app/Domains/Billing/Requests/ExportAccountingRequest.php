<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExportAccountingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('billing-accounting');
    }

    public function rules(): array
    {
        return [
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('from'));
            $to = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('to'));

            if ($to->gt($from->addDays(365))) {
                $validator->errors()->add('to', 'El período máximo de exportación es de 366 días.');
            }
        }];
    }
}
