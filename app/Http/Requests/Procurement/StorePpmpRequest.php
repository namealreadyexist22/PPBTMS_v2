<?php

namespace App\Http\Requests\Procurement;

use App\Enums\PpmpType;
use App\Models\Procurement\Office;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePpmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422));
    }

    public function rules(): array
    {
        $year = (int) now()->year;

        return [
            // Only offices this user may prepare a PPMP for
            'office_id'   => ['required', Rule::in(Office::assignableTo($this->user())->pluck('id'))],
            'fiscal_year' => ['required', 'integer', 'between:' . $year . ',' . ($year + 2)],
            'type'        => ['nullable', Rule::enum(PpmpType::class)],
            'remarks'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'office_id.in' => 'You can only create a PPMP for your own office.',
        ];
    }
}