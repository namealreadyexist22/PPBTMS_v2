<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'route'       => 'required|string|max:255',
            'category'    => 'required|string|max:255',
            'icon'        => 'required|string|max:255',
            'is_menu'     => 'nullable|boolean',
            'is_dropdown' => 'nullable|boolean',
            'submenus'    => 'nullable|array',
            'submenus.*'  => 'string|in:create,store,edit,update,show,destroy,print',
        ];
    }

    /**
     * Prepare inputs for validation (handles missing switch inputs)
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_menu'     => $this->has('is_menu'),
            'is_dropdown' => $this->has('is_dropdown'),
        ]);
    }
}
