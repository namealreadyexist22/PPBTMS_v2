<?php

namespace App\Services\Procurement;

use App\Enums\PpmpStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Hard budget control between PR lines and PPMP lines.
 * A PR line may only be charged against a line of the currently approved PPMP,
 * and never beyond that line's remaining budget.
 */
class PpmpBudgetService
{
    /** The line with the same line_uuid in the currently approved PPMP version. */
    public function currentLine(PpmpItem $line): PpmpItem
    {
        $current = PpmpItem::where('line_uuid', $line->line_uuid)
            ->whereHas('ppmp', fn ($q) => $q->where('status', PpmpStatus::Approved))
            ->first();

        if (! $current) {
            throw new ProcurementException("\"{$line->description}\" is not in an approved PPMP.");
        }

        return $current;
    }

    public function charge(PpmpItem $line, string|int|float $amount): PpmpItem
    {
        $cents = Money::toCents($amount);

        if ($cents <= 0) {
            throw new ProcurementException('Charge amount must be greater than zero.');
        }

        return DB::transaction(function () use ($line, $cents) {
            $current = $this->lockCurrentLine($line);
            $available = Money::toCents($current->estimated_budget) - Money::toCents($current->committed_amount);

            if ($cents > $available) {
                throw new ProcurementException(sprintf(
                    'Insufficient PPMP budget for "%s": requested %s, available %s.',
                    $current->description,
                    Money::format(Money::fromCents($cents)),
                    Money::format(Money::fromCents($available)),
                ));
            }

            $current->update([
                'committed_amount' => Money::fromCents(Money::toCents($current->committed_amount) + $cents),
            ]);

            return $current;
        });
    }

    /** Give budget back, e.g. when a PR is cancelled or its line reduced. */
    public function release(PpmpItem $line, string|int|float $amount): PpmpItem
    {
        $cents = Money::toCents($amount);

        return DB::transaction(function () use ($line, $cents) {
            $current = $this->lockCurrentLine($line);
            $committed = Money::toCents($current->committed_amount);

            if ($cents <= 0 || $cents > $committed) {
                throw new ProcurementException('Release amount must be between zero and the amount charged.');
            }

            $current->update(['committed_amount' => Money::fromCents($committed - $cents)]);

            return $current;
        });
    }

    /**
     * Lock the current line, then re-check (with a locking read, so not from a stale
     * snapshot) that its PPMP is still approved: an amendment may have been approved
     * while we waited for the lock.
     */
    protected function lockCurrentLine(PpmpItem $line): PpmpItem
    {
        $current = PpmpItem::whereKey($this->currentLine($line)->id)->lockForUpdate()->firstOrFail();

        $ppmp = Ppmp::whereKey($current->ppmp_id)->lockForUpdate()->firstOrFail();

        if ($ppmp->status !== PpmpStatus::Approved) {
            throw new ProcurementException('The PPMP was just amended. Please try again.');
        }

        return $current;
    }
}
