<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\Region;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Procurement\OfficialsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Signatories & BAC: the HOPE, BAC and Secretariat of each region (defaults for the documents). */
class OfficialsController extends Controller
{
    public function __construct(
        protected OfficialsService $officials
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('procurement.officials.index', [
            'roles'   => OfficialsService::ROLES,
            'regions' => collect(Region::cases())->map(fn (Region $region) => [
                'region'    => $region,
                'officials' => $this->officials->all($region),
                'canManage' => $this->officials->canManage($user, $region),
            ]),
            'users'   => User::where('is_activated', true)->with('office')->orderBy('fname')->orderBy('lname')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = ['region' => ['required', Rule::enum(Region::class)], 'bac_members' => ['nullable', 'array'], 'bac_members.*' => ['integer', 'exists:users,id']];
        foreach (array_keys(OfficialsService::ROLES) as $role) {
            $rules[$role] = ['nullable', 'integer', 'exists:users,id'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $region = Region::from($data['region']);

        if (! $this->officials->canManage($request->user(), $region)) {
            return response()->json(['status' => 'error', 'message' => "You cannot change the {$region->label()} officials."], 403);
        }

        $this->officials->set($region, $data, $data['bac_members'] ?? []);
        activity()->causedBy($request->user())->log("updated the {$region->label()} signatories and BAC");

        return response()->json(['status' => 'success', 'message' => "{$region->label()} signatories and BAC saved."]);
    }
}
