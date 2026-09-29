<?php

namespace App\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt and throw a clean JSON JSON response block.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->input('id');
        $isUpdate = !empty($userId);

        return [
            'id'         => ['nullable', 'integer', 'exists:users,id'],
            'fname'      => ['required', 'string', 'max:255'],
            'minitial'   => ['nullable', 'string', 'max:1'],
            'lname'      => ['required', 'string', 'max:255'],

            // Ignore the current user record context unique tracking pointer when updating
            'username'   => ['required', 'string', 'min:4', 'max:50', 'unique:users,username,' . ($userId ?? 'NULL')],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . ($userId ?? 'NULL')],

            // Password rules transition dynamically based on existence of creation vs update sequence state tracks
            'password'   => $isUpdate ? ['nullable', 'string', 'min:4', 'confirmed'] : ['required', 'string', 'min:4', 'confirmed'],

            'categories' => ['required', 'in:1,2'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ];
    }

    /**
     * Get customized error messages for defined validation rules handlers.
     */
    public function messages(): array
    {
        return [
            'username.unique' => 'This account username is already registered in the system.',
            'email.unique'    => 'This email address is already bound to an active profile.',
            'password.confirmed' => 'The system credentials confirmation match failed.',
        ];
    }
}
