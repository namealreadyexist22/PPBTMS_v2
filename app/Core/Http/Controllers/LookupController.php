<?php

namespace App\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Settings > Procurement Lookups: the code/name lists used by PPMP and APP forms
 * (fund sources such as "GAA 2017 - Continuing Appropriation", modes, units, categories).
 */
class LookupController extends Controller
{
    /** type => [model, label, tables (and columns) that use it] */
    protected const TYPES = [
        'fund-sources'      => [FundSource::class, 'Fund Sources', ['ppmp_items' => 'fund_source_id', 'app_items' => 'fund_source_id']],
        'procurement-modes' => [ProcurementMode::class, 'Modes of Procurement', ['ppmp_items' => 'procurement_mode_id', 'app_items' => 'procurement_mode_id']],
        'units'             => [Unit::class, 'Units', ['ppmp_items' => 'unit_id', 'items' => 'unit_id']],
        'item-categories'   => [ItemCategory::class, 'Item Categories', ['items' => 'item_category_id']],
    ];

    public function index(Request $request)
    {
        $type = $this->type($request->input('type', 'fund-sources'));
        [$model, $label, $usedIn] = self::TYPES[$type];

        $rows = $model::orderByDesc('is_active')->orderBy('name')->get()->each(function (Model $row) use ($usedIn) {
            $row->used_count = collect($usedIn)->sum(fn ($column, $table) => DB::table($table)->where($column, $row->id)->count());
        });

        return view('BackEnd.lookups.index', [
            'types' => collect(self::TYPES)->map(fn ($t) => $t[1]),
            'type'  => $type,
            'label' => $label,
            'rows'  => $rows,
            'hasFund' => $this->hasFund($type),
            // Item categories: the unit that alone may procure them (e.g. ICT Equipment -> MIS)
            'offices' => $type === 'item-categories' ? \App\Models\Procurement\Office::active()->whereNotNull('code')->orderBy('code')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->type($request->input('type'));
        [$model] = self::TYPES[$type];
        $table = (new $model)->getTable();
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'         => ['nullable', 'integer', Rule::exists($table, 'id')],
            'code'       => ['required', 'string', 'max:30', Rule::unique($table, 'code')->ignore($id)],
            'name'       => ['required', 'string', 'max:255'],
            // Fund sources: which APP their projects go to; departments: which fund they are budgeted under
            'fund_group' => [$this->hasFund($type) ? 'required' : 'nullable', Rule::enum(\App\Enums\FundGroup::class)],
            // Item categories only: procured by one unit, for every fund or one fund (e.g. COB)
            'restricted_office_id'  => ['nullable', 'integer', 'exists:offices,id'],
            'restricted_fund_group' => ['nullable', Rule::enum(\App\Enums\FundGroup::class)],
        ], ['code.unique' => 'This code is already used.']);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        $row = $id ? $model::findOrFail($id) : new $model;
        $fields = $this->hasFund($type) ? ['code', 'name', 'fund_group'] : ['code', 'name'];
        $extra = $type === 'item-categories' ? [
            'restricted_office_id'  => $request->input('restricted_office_id') ?: null,
            'restricted_fund_group' => $request->input('restricted_office_id') ? ($request->input('restricted_fund_group') ?: null) : null,
        ] : [];
        $row->fill($validator->safe()->only($fields) + $extra + ['is_active' => $request->boolean('is_active', true)])->save();

        activity()->causedBy($request->user())->performedOn($row)->log(($id ? 'updated' : 'added') . " {$type} \"{$row->name}\"");

        return response()->json(['status' => 'success', 'message' => ($id ? 'Updated' : 'Added') . " \"{$row->name}\"."]);
    }

    /** Delete only when nothing uses it; otherwise deactivate it instead. */
    public function destroy(Request $request)
    {
        $type = $this->type($request->input('type'));
        [$model, , $usedIn] = self::TYPES[$type];
        $row = $model::findOrFail($request->integer('id'));

        $used = collect($usedIn)->sum(fn ($column, $table) => DB::table($table)->where($column, $row->id)->count());
        if ($used > 0) {
            return response()->json(['status' => 'error', 'message' => "\"{$row->name}\" is used by {$used} record(s). Deactivate it instead."], 422);
        }

        activity()->causedBy($request->user())->log("deleted {$type} \"{$row->name}\"");
        $row->delete();

        return response()->json(['status' => 'success', 'message' => "Deleted \"{$row->name}\"."]);
    }

    protected function hasFund(string $type): bool
    {
        return $type === 'fund-sources';
    }

    protected function type(?string $type): string
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return $type;
    }
}
