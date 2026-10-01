<?php

namespace App\Services\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * PPMP lifecycle: office prepares (draft) -> submits -> the nearest head above it
 * (Office::approverId) approves or returns. Approved PPMPs are then visible to BAC for consolidation into the APP.
 * An approved PPMP is changed only through an amendment (new version).
 */
class PpmpService
{
    public function create(Office $office, int $fiscalYear, User $user, PpmpType $type = PpmpType::Indicative, ?string $remarks = null): Ppmp
    {
        if (! Office::assignableTo($user)->whereKey($office->id)->exists()) {
            throw new ProcurementException('You can only create a PPMP for your home office.');
        }

        if (Ppmp::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)->exists()) {
            throw new ProcurementException("{$office->shortName()} already has a PPMP for FY {$fiscalYear}. Amend it instead.");
        }

        return DB::transaction(function () use ($office, $fiscalYear, $user, $type, $remarks) {
            $ppmp = Ppmp::create([
                'ppmp_no'     => $this->number($office, $fiscalYear, 1),
                'fiscal_year' => $fiscalYear,
                'office_id'   => $office->id,
                'type'        => $type,
                'version'     => 1,
                'status'      => PpmpStatus::Draft,
                'remarks'     => $remarks,
                'created_by'  => $user->id,
                'updated_by'  => $user->id,
            ]);

            $ppmp->sign($user, 'prepared');

            return $ppmp;
        });
    }

    public function addItem(Ppmp $ppmp, array $data): PpmpItem
    {
        $this->assertEditable($ppmp);
        $this->assertItemData($data);

        return DB::transaction(function () use ($ppmp, $data) {
            $data['sort_order'] ??= (int) $ppmp->items()->max('sort_order') + 1;
            unset($data['line_uuid'], $data['committed_amount'], $data['ppmp_id']);

            $item = $ppmp->items()->create($data);
            $ppmp->recalculateTotal();

            return $item;
        });
    }

    public function updateItem(PpmpItem $item, array $data): PpmpItem
    {
        $ppmp = $item->ppmp;
        $this->assertEditable($ppmp);
        unset($data['line_uuid'], $data['committed_amount'], $data['ppmp_id']);
        $this->assertItemData(array_merge($item->only(['proc_start', 'proc_end', 'estimated_budget']), $data));

        if (isset($data['estimated_budget'])
            && Money::toCents($data['estimated_budget']) < Money::toCents($item->committed_amount)) {
            throw new ProcurementException('Estimated budget cannot be lower than the amount already charged by PRs ('.Money::format($item->committed_amount).').');
        }

        return DB::transaction(function () use ($item, $ppmp, $data) {
            $item->update($data);
            $ppmp->recalculateTotal();

            return $item;
        });
    }

    public function removeItem(PpmpItem $item): void
    {
        $ppmp = $item->ppmp;
        $this->assertEditable($ppmp);

        if (Money::toCents($item->committed_amount) > 0) {
            throw new ProcurementException('This line already has PR charges and cannot be removed.');
        }

        DB::transaction(function () use ($item, $ppmp) {
            $item->delete();
            $ppmp->recalculateTotal();
        });
    }

    public function submit(Ppmp $ppmp, User $user, ?string $remarks = null): Ppmp
    {
        $this->assertEditable($ppmp);

        if (! $ppmp->items()->exists()) {
            throw new ProcurementException('Add at least one procurement project before submitting.');
        }

        return DB::transaction(function () use ($ppmp, $user, $remarks) {
            $ppmp->update([
                'status'       => PpmpStatus::Submitted,
                'submitted_at' => now(),
                'updated_by'   => $user->id,
            ]);
            $ppmp->sign($user, 'submitted', $remarks);

            return $ppmp;
        });
    }

    public function returnToOffice(Ppmp $ppmp, User $approver, string $remarks): Ppmp
    {
        $this->assertStatus($ppmp, PpmpStatus::Submitted);
        $this->assertIsApprover($ppmp, $approver);

        return DB::transaction(function () use ($ppmp, $approver, $remarks) {
            $ppmp->update(['status' => PpmpStatus::Returned, 'updated_by' => $approver->id]);
            $ppmp->sign($approver, 'returned', $remarks);

            return $ppmp;
        });
    }

    public function approve(Ppmp $ppmp, User $approver, ?string $remarks = null): Ppmp
    {
        $this->assertStatus($ppmp, PpmpStatus::Submitted);
        $this->assertIsApprover($ppmp, $approver);

        return DB::transaction(function () use ($ppmp, $approver, $remarks) {
            if ($ppmp->amended_from_id) {
                $this->carryOverCharges($ppmp);
            }

            $ppmp->update([
                'status'      => PpmpStatus::Approved,
                'approved_at' => now(),
                'updated_by'  => $approver->id,
            ]);
            $ppmp->sign($approver, 'approved', $remarks);

            return $ppmp;
        });
    }

    /** Start an amendment: copy the approved PPMP into a new draft version. */
    public function amend(Ppmp $ppmp, User $user, ?PpmpType $type = null, ?string $remarks = null): Ppmp
    {
        $this->assertStatus($ppmp, PpmpStatus::Approved);

        $openVersion = Ppmp::where('office_id', $ppmp->office_id)
            ->where('fiscal_year', $ppmp->fiscal_year)
            ->whereIn('status', [PpmpStatus::Draft, PpmpStatus::Submitted, PpmpStatus::Returned])
            ->exists();

        if ($openVersion) {
            throw new ProcurementException('An amendment for this PPMP is already in progress.');
        }

        return DB::transaction(function () use ($ppmp, $user, $type, $remarks) {
            $version = (int) Ppmp::withTrashed()
                ->where('office_id', $ppmp->office_id)
                ->where('fiscal_year', $ppmp->fiscal_year)
                ->max('version') + 1;

            $copy = Ppmp::create([
                'ppmp_no'         => $this->number($ppmp->office, $ppmp->fiscal_year, $version),
                'fiscal_year'     => $ppmp->fiscal_year,
                'office_id'       => $ppmp->office_id,
                'type'            => $type ?? $ppmp->type,
                'version'         => $version,
                'amended_from_id' => $ppmp->id,
                'status'          => PpmpStatus::Draft,
                'total_budget'    => $ppmp->total_budget,
                'remarks'         => $remarks,
                'created_by'      => $user->id,
                'updated_by'      => $user->id,
            ]);

            foreach ($ppmp->items as $item) {
                // Same line_uuid keeps PR charges attached to the line across versions.
                $copy->items()->create($item->only($item->getFillable()));
            }

            $copy->sign($user, 'prepared', $remarks);

            return $copy;
        });
    }

    /**
     * On approving an amendment, take over the PR charges of the version it replaces
     * (charges may have been made while the amendment was being prepared), then
     * supersede that version. Runs inside the approve() transaction.
     */
    protected function carryOverCharges(Ppmp $amendment): void
    {
        $previous = Ppmp::whereKey($amendment->amended_from_id)->lockForUpdate()->firstOrFail();

        if ($previous->status !== PpmpStatus::Approved) {
            throw new ProcurementException('The PPMP being amended is no longer the approved version.');
        }

        $oldLines = PpmpItem::where('ppmp_id', $previous->id)->lockForUpdate()->get()->keyBy('line_uuid');
        $newLines = $amendment->items()->get()->keyBy('line_uuid');

        foreach ($oldLines as $uuid => $old) {
            $committed = Money::toCents($old->committed_amount);
            $new = $newLines->get($uuid);

            if (! $new) {
                if ($committed > 0) {
                    throw new ProcurementException("Removed line \"{$old->description}\" already has PR charges of ".Money::format($old->committed_amount).'.');
                }

                continue;
            }

            if (Money::toCents($new->estimated_budget) < $committed) {
                throw new ProcurementException("Line \"{$new->description}\" budget is below its PR charges of ".Money::format($old->committed_amount).'.');
            }

            $new->update(['committed_amount' => $old->committed_amount]);
        }

        $previous->update(['status' => PpmpStatus::Superseded]);
    }

    protected function number(Office $office, int $fiscalYear, int $version): string
    {
        // YY-office number-series, e.g. 27-05000-01 (series = version; amendments are 02, 03, ...)
        return sprintf('%02d-%s-%02d', $fiscalYear % 100, $office->code, $version);
    }

    protected function assertItemData(array $data): void
    {
        if (isset($data['estimated_budget']) && Money::toCents($data['estimated_budget']) <= 0) {
            throw new ProcurementException('Estimated budget must be greater than zero.');
        }

        if (! empty($data['proc_start']) && ! empty($data['proc_end'])
            && strtotime((string) $data['proc_end']) < strtotime((string) $data['proc_start'])) {
            throw new ProcurementException('End of procurement activity cannot be before its start.');
        }
    }

    protected function assertEditable(Ppmp $ppmp): void
    {
        if (! $ppmp->status->isEditable()) {
            throw new ProcurementException("PPMP {$ppmp->ppmp_no} is {$ppmp->status->value} and can no longer be edited.");
        }
    }

    protected function assertStatus(Ppmp $ppmp, PpmpStatus $expected): void
    {
        if ($ppmp->status !== $expected) {
            throw new ProcurementException("PPMP {$ppmp->ppmp_no} must be {$expected->value} (currently {$ppmp->status->value}).");
        }
    }

    protected function assertIsApprover(Ppmp $ppmp, User $user): void
    {
        $office = $ppmp->office;
        $approverId = $office->approverId();

        if (! $approverId) {
            throw new ProcurementException('No approving head is set above '.$office->name.'. Set the head in Offices.');
        }

        if ($approverId !== (int) $user->id) {
            $approver = User::find($approverId);

            throw new ProcurementException('Only '.($approver?->fullname ?? 'the approving head').' can approve or return this PPMP.');
        }
    }
}
