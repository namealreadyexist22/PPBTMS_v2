<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\Office;
use App\Services\Procurement\CobReportService;
use App\Services\Procurement\CobSignatoryService;
use Illuminate\Http\Request;

/**
 * Corporate Operating Budget printouts (browser Print / Save as PDF): the Breakdown per Office /
 * Department, and per department its Budget Proposal summary, Details of MOOE (Form B) and
 * Details of CO (Form C), from the PPMPs and budget allocations.
 */
class CobReportController extends Controller
{
    public function __construct(
        protected CobReportService $reports
    ) {}

    public function breakdown(Request $request)
    {
        [$fiscalYear, $drafts] = $this->filters($request);

        return view('procurement.budget.cob_breakdown', [
            'fiscalYear' => $fiscalYear,
            'drafts'     => $drafts,
            'years'      => $this->years(),
            'funds'      => $this->reports->breakdown($fiscalYear, $drafts),
            'signatories' => app(CobSignatoryService::class)->all(),
        ]);
    }

    public function proposal(Request $request, Office $department)
    {
        abort_unless($department->isDepartment(), 404);
        [$fiscalYear, $drafts] = $this->filters($request);

        return view('procurement.budget.cob_proposal', $this->reports->proposal($department, $fiscalYear, $drafts) + [
            'fiscalYear'  => $fiscalYear,
            'drafts'      => $drafts,
            'years'       => $this->years(),
            // To switch department from the toolbar
            'departments' => Office::active()->where('type', 'department')->orderBy('acronym')->get(),
            'signatories' => app(CobSignatoryService::class)->all(),
            'submitters'  => app(CobSignatoryService::class)->submitters($department),
        ]);
    }

    /**
     * The whole organization in one printout: the Breakdown, then every department's Budget Proposal
     * (Corporate Fund then SIDA Fund, in office-number order), departments without PPMPs left out.
     */
    public function organization(Request $request)
    {
        [$fiscalYear, $drafts] = $this->filters($request);
        $show = in_array($request->input('show'), ['breakdown', 'summary', 'performance', 'mooe', 'co'], true) ? $request->input('show') : 'all';
        $funds = $this->reports->breakdown($fiscalYear, $drafts);
        $signatories = app(CobSignatoryService::class);

        $proposals = $show === 'breakdown' ? collect() : collect($funds)->flatMap(fn ($f) => $f['rows'])
            ->map(fn ($row) => $this->reports->proposal($row['department'], $fiscalYear, $drafts))
            ->filter(fn ($p) => ! empty($p['blocks']))
            ->map(fn ($p) => $p + ['submitters' => $signatories->submitters($p['department'])])
            ->values();

        return view('procurement.budget.cob_organization', [
            'fiscalYear'  => $fiscalYear,
            'drafts'      => $drafts,
            'show'        => $show,
            'years'       => $this->years(),
            'funds'       => $funds,
            'proposals'   => $proposals,
            'signatories' => $signatories->all(),
        ]);
    }

    /** [fiscal year, with drafts] */
    protected function filters(Request $request): array
    {
        return [(int) ($request->input('fy') ?: now()->year + 1), $request->boolean('drafts')];
    }

    protected function years(): array
    {
        return range(now()->year - 1, now()->year + 2);
    }
}
