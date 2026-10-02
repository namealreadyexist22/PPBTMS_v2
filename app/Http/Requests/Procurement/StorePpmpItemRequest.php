<?php

namespace App\Http\Requests\Procurement;

use App\Enums\ProjectType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * One procurement project row of the PPMP (GPPB revised format).
 */
class StorePpmpItemRequest extends FormRequest
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

    /** <input type="month"> sends "2027-01"; store the first day of that month. */
    protected function prepareForValidation(): void
    {
        foreach (['proc_start', 'proc_end'] as $field) {
            if (preg_match('/^\d{4}-\d{2}$/', (string) $this->input($field))) {
                $this->merge([$field => $this->input($field) . '-01']);
            }
        }

        $unitCost = str_replace(',', '', (string) $this->input('unit_cost'));
        $budget = str_replace(',', '', (string) $this->input('estimated_budget'));

        // Quantity x unit price fills the budget (the service recomputes it too)
        $cents = \App\Models\Procurement\PpmpItem::computedBudgetCents($this->input('quantity'), $unitCost === '' ? null : $unitCost);
        if ($cents !== null && is_numeric($unitCost)) {
            $budget = \App\Support\Money::fromCents($cents);
        }

        $this->merge([
            'pre_proc_conference' => $this->boolean('pre_proc_conference'),
            'unit_cost'           => $unitCost === '' ? null : $unitCost,
            'estimated_budget'    => $budget,
        ]);
    }

    public function rules(): array
    {
        return [
            'id'                   => ['nullable', 'integer'],
            'ppmp_pap_id'          => ['required', 'integer'],
            'item_id'              => ['nullable', 'integer', 'exists:items,id'],
            'description'          => ['required', 'string', 'max:2000'],
            'project_type'         => ['required', Rule::enum(ProjectType::class)],
            'quantity'             => ['nullable', 'numeric', 'min:0'],
            'unit_id'              => ['nullable', 'integer', 'exists:units,id'],
            'unit_cost'            => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'quantity_size'        => ['nullable', 'string', 'max:2000'],   // specifications
            'procurement_mode_id'  => ['required', 'integer', 'exists:procurement_modes,id'],
            'pre_proc_conference'  => ['boolean'],
            'proc_start'           => ['required', 'date'],
            'proc_end'             => ['required', 'date', 'after_or_equal:proc_start'],
            'delivery_period'      => ['nullable', 'string', 'max:255'],
            'fund_source_id'       => ['required', 'integer', 'exists:fund_sources,id'],
            'allotment_class'      => ['required', Rule::enum(\App\Enums\AllotmentClass::class)],
            'estimated_budget'     => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'supporting_documents' => ['nullable', 'string', 'max:1000'],
            'remarks'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ppmp_pap_id'         => 'PAP',
            'unit_cost'           => 'unit price',
            'quantity_size'       => 'specifications',
            'procurement_mode_id' => 'mode of procurement',
            'fund_source_id'      => 'source of funds',
            'proc_start'          => 'start of procurement activity',
            'proc_end'            => 'end of procurement activity',
        ];
    }
}
