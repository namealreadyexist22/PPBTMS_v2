<?php

namespace App\Http\Controllers\Procurement;

use App\DataTables\Procurement\PpmpDataTable;
use App\Enums\PpmpType;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePpmpRequest;
use App\Models\Procurement\Office;
use App\Services\Procurement\PpmpService;
use Illuminate\Http\Request;

class PpmpController extends Controller
{
    public function index(PpmpDataTable $dataTable)
    {
        return $dataTable->render('procurement.ppmp.index');
    }

    public function destroy(){

    }

    public function __construct(
        protected PpmpService $ppmpService
    ) {}

    public function entry(Request $request)
    {
        return view('procurement.ppmp.extras.ppmp_entry', [
            'modalName' => 'PPMP_ENTRY_MODAL',
            'offices'   => Office::assignableTo($request->user())->get(),
            'types'     => PpmpType::cases(),
            'years'     => range(now()->year, now()->year + 2),
        ]);
    }

    public function store(StorePpmpRequest $request)
    {
        try {
            $ppmp = $this->ppmpService->create(
                Office::findOrFail($request->office_id),
                (int) $request->fiscal_year,
                $request->user(),
                PpmpType::from($request->type),
                $request->remarks,
            );

            return response()->json([
                'status'  => 'success',
                'message' => "PPMP {$ppmp->ppmp_no} created.",
                'uuid'    => $ppmp->uuid,
            ]);
        } catch (ProcurementException $e) {
            // Business rule (e.g. office already has a PPMP for that year)
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

}

