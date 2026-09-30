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
            'code'         => ['required', 'string', 'max:30', Rule::unique('offices', 'code')->ignore($id)],
            'name'         => ['required', 'string', 'max:255'],
            // A section's parent must be a division (a top-level office), never itself
            'parent_id'    => [
                'nullable',
                Rule::exists('offices', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$id])),
            ],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The division must be a top-level office.',
            'parent_id.not_in' => 'An office cannot be its own division.',
        ];
    }

    public function withValidator($validator): void
    {
        // Only two levels: a division that already has sections cannot become a section
        $validator->after(function ($validator) {
            $id = $this->input('id');

            if ($id && $this->filled('parent_id') && Office::where('parent_id', $id)->exists()) {
                $validator->errors()->add('parent_id', 'This office has sections under it, so it must stay a division.');
            }
        });
    }
}
