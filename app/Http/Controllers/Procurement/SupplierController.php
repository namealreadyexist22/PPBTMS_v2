<?php

namespace App\Http\Controllers\Procurement;

use App\DataTables\Procurement\SuppliersDataTable;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Supplier;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Supplier directory: suppliers/dealers picked on an AOQ so their details are not typed again.
 * Suppliers first entered on an AOQ are added here automatically. Abstracts keep their own copy
 * of the details, so changing a supplier here does not change abstracts already made.
 */
class SupplierController extends Controller
{
    /** The page, or (for the table's requests) one page of suppliers as JSON. */
    public function index(SuppliersDataTable $dataTable)
    {
        return $dataTable->render('procurement.suppliers.index');
    }

    public function entry(Request $request)
    {
        return view('procurement.suppliers.entry', [
            'supplier' => $request->filled('id') ? Supplier::findOrFail($request->integer('id')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'          => ['nullable', 'integer', 'exists:suppliers,id'],
            'name'        => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($id)],
            'address'     => ['nullable', 'string', 'max:500'],
            'tin'         => ['nullable', 'string', 'max:50'],
            'contact'     => ['nullable', 'string', 'max:255'],
            'philgeps_no' => ['nullable', 'string', 'max:50'],
        ], ['name.required' => 'Enter the supplier\'s name.', 'name.unique' => 'This supplier is already on the list.'], ['philgeps_no' => 'PhilGEPS registration no.']);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        $supplier = $id ? Supplier::findOrFail($id) : new Supplier(['created_by' => $request->user()->id]);
        $supplier->fill($validator->safe()->except('id') + ['is_active' => $request->boolean('is_active', true), 'updated_by' => $request->user()->id])->save();

        activity()->causedBy($request->user())->performedOn($supplier)->log(($id ? 'updated' : 'added') . " supplier \"{$supplier->name}\"");

        return response()->json(['status' => 'success', 'message' => ($id ? 'Updated' : 'Added') . " \"{$supplier->name}\"."]);
    }

    /** Delete only when no abstract uses it; otherwise deactivate it instead. */
    public function destroy(Request $request)
    {
        $supplier = Supplier::withCount('aoqSuppliers')->findOrFail($request->integer('id'));

        if ($supplier->aoq_suppliers_count > 0) {
            return response()->json(['status' => 'error', 'message' => "\"{$supplier->name}\" is on {$supplier->aoq_suppliers_count} abstract(s). Deactivate it instead."], 422);
        }

        activity()->causedBy($request->user())->log("deleted supplier \"{$supplier->name}\"");
        $supplier->delete();

        return response()->json(['status' => 'success', 'message' => "Deleted \"{$supplier->name}\"."]);
    }
}
