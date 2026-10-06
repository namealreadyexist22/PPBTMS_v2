<?php

namespace App\Core\Http\Controllers;

use App\Core\Http\Requests\StoreOfficeRequest;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Office;
use App\Models\User;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    /** Settings > Organization: the tree of departments, divisions and sections. */
    public function index(Request $request)
    {
        $offices = Office::with('head')->withCount('users')->get();
        $childrenOf = $offices->whereNotNull('parent_id')->groupBy('parent_id');

        // In office-number order; a department without a number sorts by its first unit's
        $sortKey = function (Office $office) use (&$sortKey, $childrenOf): string {
            return $office->code ?? ($childrenOf->get($office->id, collect())->map($sortKey)->min() ?? 'zzzzz');
        };

        return view('BackEnd.offices.index', [
            'roots'      => $offices->whereNull('parent_id')->sortBy($sortKey)->values(),
            'childrenOf' => $childrenOf->map(fn ($children) => $children->sortBy($sortKey)->values()),
            'canDelete'  => $request->user()->canAccessPermission('menu.offices-destroy'),
        ]);
    }

    public function entry(Request $request)
    {
        $office = $request->filled('id') ? Office::findOrFail($request->id) : null;

        return view('BackEnd.offices.extras.office_entry', [
            'modalName' => 'OFFICE_ENTRY_MODAL',
            'office'    => $office,
            // Adding a unit: its type and the unit it goes under
            'type'      => $office->type ?? (array_key_exists((string) $request->input('type'), Office::TYPES) ? $request->input('type') : 'department'),
            'parentId'  => $office->parent_id ?? ($request->integer('parent_id') ?: null),
            // Any office except this one and its own sub-offices
            'parents'   => Office::query()
                ->when($office, fn ($q) => $q->whereKeyNot(Office::withDescendantIds([$office->id])))
                ->orderBy('code')
                ->get(),
            'users'     => User::where('is_activated', true)->orderBy('lname')->get(),
        ]);
    }

    public function store(StoreOfficeRequest $request)
    {
        $data = $request->safe()->except('id');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_consolidating'] = $request->boolean('is_consolidating');
        // Only a department has a budget fund (COB, or SIDA for the SIDA departments)
        $data['budget_fund'] = $data['type'] === 'department' ? ($data['budget_fund'] ?? 'regular') : null;

        $office = $request->filled('id') ? Office::findOrFail($request->id) : new Office();
        $isNew = ! $office->exists;
        $office->fill($data)->save();

        activity()->causedBy($request->user())->performedOn($office)
            ->log(($isNew ? 'created' : 'updated') . " {$office->type} \"{$office->code}\"");

        return response()->json([
            'status'  => 'success',
            'message' => $isNew ? "{$office->typeLabel()} created." : "{$office->typeLabel()} updated.",
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => ['required', 'integer', 'exists:offices,id']]);

        $office = Office::withCount(['children', 'users', 'ppmps'])->findOrFail($request->id);

        if ($office->children_count || $office->users_count || $office->ppmps_count) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This unit still has units under it, users or PPMPs. Deactivate it instead.',
            ], 422);
        }

        activity()->causedBy($request->user())->performedOn($office)->log("deleted office \"{$office->code}\"");
        $office->delete();

        return response()->json(['status' => 'success', 'message' => 'Unit deleted.']);
    }
}
