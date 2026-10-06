<?php

namespace App\Core\Http\Requests;

use App\Models\Procurement\Office;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreOfficeRequest extends FormRequest
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
        $id = $this->input('id');

        return [
            'id'           => ['nullable', 'integer', 'exists:offices,id'],
            // Office number used in PPMP numbers (YY-xxxxx-NN); digits only, keeps leading zeros
            'code'         => ['required', 'string', 'regex:/^\d{1,10}$/', Rule::unique('offices', 'code')->ignore($id)],
            'acronym'      => ['nullable', 'string', 'max:30'],
            'name'         => ['required', 'string', 'max:255'],
            // Never itself or one of its own sub-offices (that would make a loop)
            'parent_id'    => [
                'nullable',
                'required_unless:type,department',
                Rule::exists('offices', 'id'),
                Rule::notIn($id ? Office::withDescendantIds([$id])->all() : []),
            ],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type'         => ['required', Rule::in(array_keys(Office::TYPES))],
            'budget_fund'  => ['nullable', Rule::enum(\App\Enums\FundGroup::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'       => 'The office number must contain digits only (e.g. 05000).',
            'code.unique'      => 'This office number is already used.',
            'parent_id.not_in' => 'The parent cannot be this office or one of its sub-offices.',
            'parent_id.required_unless' => 'A division or section must be under a department or division.',
        ];
    }
}
