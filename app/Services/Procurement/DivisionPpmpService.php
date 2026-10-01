<?php

namespace App\Services\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\DivisionPpmp;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Combines the section PPMPs under a consolidating office into its Division PPMP.
 * The division head approves all submitted section PPMPs at once; that creates
 * "PPMP NO. 1". Approving later amendments creates NO. 2, NO. 3, ... (each a
 * snapshot of the section PPMP versions in effect), superseding the previous number.
 */
class DivisionPpmpService
{
    public function __construct(protected PpmpService $ppmps) {}

    /** The current (latest approved) Division PPMP, if any. */
    public function current(Office $office, int $fiscalYear): ?DivisionPpmp
    {
        return DivisionPpmp::where('office_id', $office->id)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', DivisionPpmp::STATUS_APPROVED)
            ->first();
    }

    /** Every section PPMP (latest version per office) combined into this office, for the overview. */
    public function sectionPpmps(Office $office, int $fiscalYear): Collection
    {
        return Ppmp::with('office')
            ->whereIn('office_id', $office->consolidatedOfficeIds())
            ->where('fiscal_year', $fiscalYear)
            ->where('status', '!=', PpmpStatus::Superseded)
            ->orderBy('version')
            ->get()
            ->groupBy('office_id')
            ->map(fn (Collection $versions) => $versions->last())   // newest open or approved version
            ->sortBy(fn (Ppmp $ppmp) => $ppmp->office->code)
            ->values();
    }

    /** Submitted section PPMPs waiting for the division head. */
    public function pending(Office $office, int $fiscalYear): Collection
    {
        return Ppmp::with('office')
            ->whereIn('office_id', $office->consolidatedOfficeIds())
            ->where('fiscal_year', $fiscalYear)
            ->where('status', PpmpStatus::Submitted)
            ->get();
    }

    /**
     * What the next Division PPMP would contain if the pending ones were approved now:
     * the approved version of each section, replaced by its submitted amendment.
     */
    public function preview(Office $office, int $fiscalYear): Collection
    {
        return Ppmp::with('office')
            ->whereIn('office_id', $office->consolidatedOfficeIds())
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', [PpmpStatus::Approved, PpmpStatus::Submitted])
            ->orderBy('version')
            ->get()
            ->groupBy('office_id')
            ->map(fn (Collection $versions) => $versions->last())
            ->sortBy(fn (Ppmp $ppmp) => $ppmp->office->code)
            ->values();
    }

    public function approve(Office $office, int $fiscalYear, User $head, User $preparedBy, PpmpType $type = PpmpType::Final, ?string $remarks = null): DivisionPpmp
    {
        if ((int) $office->head_user_id !== (int) $head->id) {
            throw new ProcurementException("Only the head of {$office->name} can approve its Division PPMP.");
        }

        $pending = $this->pending($office, $fiscalYear);

        if ($pending->isEmpty()) {
            throw new ProcurementException('There are no submitted section PPMPs to approve.');
        }

        return DB::transaction(function () use ($office, $fiscalYear, $head, $preparedBy, $type, $remarks, $pending) {
            foreach ($pending as $ppmp) {
                $this->ppmps->approve($ppmp, $head, $remarks);   // also supersedes amended versions
            }

            $previous = DivisionPpmp::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)
                ->lockForUpdate()->orderByDesc('ppmp_number')->first();
            $previous?->update(['status' => DivisionPpmp::STATUS_SUPERSEDED]);

            $sections = Ppmp::whereIn('office_id', $office->consolidatedOfficeIds())
                ->where('fiscal_year', $fiscalYear)
                ->where('status', PpmpStatus::Approved)
                ->get();

            $division = DivisionPpmp::create([
                'office_id'    => $office->id,
                'fiscal_year'  => $fiscalYear,
                'ppmp_number'  => ($previous?->ppmp_number ?? 0) + 1,
                'type'         => $type,
                'status'       => DivisionPpmp::STATUS_APPROVED,
                'total_budget' => Money::fromCents($sections->sum(fn (Ppmp $ppmp) => Money::toCents($ppmp->total_budget))),
                'remarks'      => $remarks,
                'approved_at'  => now(),
                'created_by'   => $head->id,
            ]);
            $division->ppmps()->attach($sections->pluck('id'));

            // Form signatories: Prepared by (the authorized person) and Submitted by (the division head)
            $division->sign($preparedBy, 'prepared');
            $division->sign($head, 'submitted', $remarks);

            return $division;
        });
    }
}
