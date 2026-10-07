<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Item;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\Unit;
use App\Support\Money;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Standard items: articles the agency regularly buys, with the standard unit cost and the
 * specifications set by the TWG. PPMP projects that pick one take its unit, price and specs.
 * Changing a standard cost does not change projects already saved; they take it when re-saved.
 */
class StandardItemController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with(['unit', 'category'])->withCount('ppmpItems')
            ->when($request->filled('category'), fn ($q) => $q->where('item_category_id', $request->integer('category')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%')->orWhere('code', 'like', '%' . $request->input('q') . '%')))
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('procurement.items.index', [
            'items'      => $items,
            'categories' => ItemCategory::orderBy('name')->get(),
        ]);
    }

    public function entry(Request $request)
    {
        $item = $request->filled('id') ? Item::findOrFail($request->id) : null;

        return view('procurement.items.entry', [
            'item'       => $item,
            'units'      => Unit::where('is_active', true)->when($item, fn ($q) => $q->orWhereKey($item->unit_id))->orderBy('name')->get(),
            'categories' => ItemCategory::where('is_active', true)->when($item?->item_category_id, fn ($q) => $q->orWhereKey($item->item_category_id))->orderBy('name')->get(),
            'types'      => ProjectType::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['standard_unit_cost' => str_replace(',', '', (string) $request->input('standard_unit_cost')) ?: null]);
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'                 => ['nullable', 'integer', 'exists:items,id'],
            'code'               => ['required', 'string', 'max:30', Rule::unique('items', 'code')->ignore($id)],
            'name'               => ['required', 'string', 'max:255'],
            'item_category_id'   => ['nullable', 'integer', 'exists:item_categories,id'],
            'unit_id'            => ['required', 'integer', 'exists:units,id'],
            'project_type'       => ['required', Rule::enum(ProjectType::class)],
            'standard_unit_cost' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'specifications'     => ['nullable', 'string', 'max:5000'],
            'twg_reference'      => ['nullable', 'string', 'max:255'],
            'twg_approved_at'    => ['nullable', 'date'],
        ], ['code.unique' => 'This item code is already used.'], ['item_category_id' => 'category', 'unit_id' => 'unit', 'standard_unit_cost' => 'standard unit cost']);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        $item = $id ? Item::findOrFail($id) : new Item();
        $oldCost = $item->standard_unit_cost;
        $item->fill($validator->safe()->except('id') + ['is_active' => $request->boolean('is_active', true), 'updated_by' => $request->user()->id])->save();

        $log = ($id ? 'updated' : 'added') . " standard item \"{$item->code} {$item->name}\"";
        if ($id && (string) $oldCost !== (string) $item->standard_unit_cost) {
            $log .= ': standard cost ₱' . Money::format($oldCost) . ' → ₱' . Money::format($item->standard_unit_cost);
        }
        activity()->causedBy($request->user())->performedOn($item)->log($log);

        return response()->json(['status' => 'success', 'message' => ($id ? 'Updated' : 'Added') . " \"{$item->name}\"."]);
    }

    /** Delete only when no PPMP project uses it; otherwise deactivate it instead. */
    public function destroy(Request $request)
    {
        $item = Item::withCount('ppmpItems')->findOrFail($request->integer('id'));

        if ($item->ppmp_items_count > 0) {
            return response()->json(['status' => 'error', 'message' => "\"{$item->name}\" is used by {$item->ppmp_items_count} PPMP project(s). Deactivate it instead."], 422);
        }

        activity()->causedBy($request->user())->log("deleted standard item \"{$item->code} {$item->name}\"");
        $item->delete();

        return response()->json(['status' => 'success', 'message' => "Deleted \"{$item->name}\"."]);
    }
}
