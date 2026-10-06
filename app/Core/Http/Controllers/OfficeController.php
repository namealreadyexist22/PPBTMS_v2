<?php

namespace App\Core\Http\Controllers;

use App\Core\DataTables\OfficesDataTable;
use App\Core\Http\Requests\StoreOfficeRequest;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Office;
use App\Models\User;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index(OfficesDataTable $dataTable)
    {
        return $dataTable->render('BackEnd.offices.index');
    }

    public function entry(Request $request)
    {
        $office = $request->filled('id') ? Office::findOrFail($request->id) : null;

        return view('BackEnd.offices.extras.office_entry', [
            'modalName' => 'OFFICE_ENTRY_MODAL',
            'office'    => $office,
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
        $data['is_department'] = $request->boolean('is_department');
        // A department is budgeted under one fund: COB, or SIDA for the SIDA departments
        $data['budget_fund'] = $data['is_department'] ? (\App\Enums\FundGroup::tryFrom((string) $request->input('budget_fund')) ?? \App\Enums\FundGroup::Regular) : null;

        $office = $request->filled('id') ? Office::findOrFail($request->id) : new Office();
        $isNew = ! $office->exists;
        $office->fill($data)->save();

        activity()->causedBy($request->user())->performedOn($office)
            ->log(($isNew ? 'created' : 'updated') . " office \"{$office->code}\"");

        return response()->json([
            'status'  => 'success',
            'message' => $isNew ? 'Office created.' : 'Office updated.',
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => ['required', 'integer', 'exists:offices,id']]);

        $office = Office::withCount(['children', 'users', 'ppmps'])->findOrFail($request->id);

        if ($office->children_count || $office->users_count || $office->ppmps_count) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This office still has sections, users or PPMPs. Deactivate it instead.',
            ], 422);
        }

        activity()->causedBy($request->user())->performedOn($office)->log("deleted office \"{$office->code}\"");
        $office->delete();

        return response()->json(['status' => 'success', 'message' => 'Office deleted.']);
    }
}
