<?php

namespace App\Services\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\PpmpPap;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Section PPMP lifecycle: a section prepares its PPMP (PAPs and their projects) ->
 * submits -> the head of its consolidating office returns it, or approves it as part
 * of the Division PPMP (DivisionPpmpService). An approved PPMP is changed only
 * through an amendment (new version), which goes into the next Division PPMP number.
 */
class PpmpService
{
    public function create(Office $office, int $fiscalYear, User $user, PpmpType $type = PpmpType::Final, ?string $remarks = null): Ppmp
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

    /** Next PAP code for this section and year: YY-office number-NN, e.g. 26-05012-03. */
    public function suggestPapCode(Ppmp $ppmp): string
    {
        $prefix = sprintf('%02d-%s-', $ppmp->fiscal_year % 100, $ppmp->office->code);

        $highest = PpmpPap::whereIn('ppmp_id', Ppmp::withTrashed()->where('office_id', $ppmp->office_id)->where('fiscal_year', $ppmp->fiscal_year)->select('id'))
            ->where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(fn ($code) => (int) substr($code, strlen($prefix)))
            ->max();

        return $prefix . str_pad((string) (($highest ?? 0) + 1), 2, '0', STR_PAD_LEFT);
    }

    public function addPap(Ppmp $ppmp, string $code, string $title): PpmpPap
    {
        $this->assertEditable($ppmp);
        $this->assertPapCodeFree($ppmp, $code);

        return $ppmp->paps()->create([
            'code'       => trim($code),
            'title'      => trim($title),
            'sort_order' => (int) $ppmp->paps()->max('sort_order') + 1,
        ]);
    }

    public function updatePap(PpmpPap $pap, string $code, string $title): PpmpPap
    {
        $this->assertEditable($pap->ppmp);
        $this->assertPapCodeFree($pap->ppmp, $code, $pap->id);

        $pap->update(['code' => trim($code), 'title' => trim($title)]);

        return $pap;
    }

    public function removePap(PpmpPap $pap): void
    {
        $this->assertEditable($pap->ppmp);

        if ($pap->items()->exists()) {
            throw new ProcurementException("PAP {$pap->code} still has projects. Remove or move them first.");
        }

        $pap->delete();
    }

    public function addItem(Ppmp $ppmp, array $data): PpmpItem
    {
        $this->assertEditable($ppmp);
        $this->assertItemData($data);
        $this->assertPapBelongs($ppmp, $data['ppmp_pap_id'] ?? null);

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

        if (array_key_exists('ppmp_pap_id', $data)) {
            $this->assertPapBelongs($ppmp, $data['ppmp_pap_id']);
        }

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

    /**
     * Delete a PPMP that was never submitted (a draft or an unsubmitted amendment).
     * Removed for good so its number and version can be used again.
     */
    public function deleteDraft(Ppmp $ppmp): void
    {
        if ($ppmp->status !== PpmpStatus::Draft) {
            throw new ProcurementException("Only a draft PPMP can be deleted. {$ppmp->ppmp_no} is {$ppmp->status->value}.");
        }

        DB::transaction(function () use ($ppmp) {
            $ppmp->signatories()->delete();
            $ppmp->forceDelete();   // items cascade
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

            $papIds = [];
            foreach ($ppmp->paps as $pap) {
                $papIds[$pap->id] = $copy->paps()->create($pap->only(['code', 'title', 'sort_order']))->id;
            }

            foreach ($ppmp->items as $item) {
                // Same line_uuid keeps PR charges attached to the line across versions.
                $copy->items()->create(array_merge($item->only($item->getFillable()), [
                    'ppmp_pap_id' => $papIds[$item->ppmp_pap_id] ?? null,
                ]));
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

    /**
     * Section PPMP reference, e.g. 05012-2026-V1 (V2, V3 for amendments). The official
     * "PPMP NO." belongs to the Division PPMP; YY-office number-NN codes are PAP codes.
     */
    protected function number(Office $office, int $fiscalYear, int $version): string
    {
        return sprintf('%s-%d-V%d', $office->code, $fiscalYear, $version);
    }

    protected function assertPapCodeFree(Ppmp $ppmp, string $code, ?int $ignoreId = null): void
    {
        if (trim($code) === '') {
            throw new ProcurementException('The PAP code is required.');
        }

        $taken = $ppmp->paps()->where('code', trim($code))->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();

        if ($taken) {
            throw new ProcurementException("PAP {$code} is already in this PPMP.");
        }
    }

    protected function assertPapBelongs(Ppmp $ppmp, $papId): void
    {
        if (! $papId || ! $ppmp->paps()->whereKey($papId)->exists()) {
            throw new ProcurementException('Choose the PAP this project belongs to.');
        }
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
