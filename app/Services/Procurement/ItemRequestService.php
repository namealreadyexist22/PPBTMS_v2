<?php

namespace App\Services\Procurement;

use App\Enums\ItemRequestStatus;
use App\Enums\PpmpStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Item;
use App\Models\Procurement\ItemRequest;
use App\Models\Procurement\Office;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\PpmpItemDistribution;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Item requests: an office asks for a standard item the office designated for its category procures
 * (e.g. SRED asks MIS for 3 desktop computers). The procuring office approves all, part or none of it,
 * and the approved quantity is added to the requesting office's share in the distribution of the
 * procuring office's PPMP project for that item and fiscal year.
 */
class ItemRequestService
{
    /**
     * Standard items another office procures for the office: active, of a category with a designated
     * unit, other than items the office itself procures (it is that unit or under it).
     */
    public function requestableItems(Office $office): Collection
    {
        return Item::active()->with(['category.restrictedOffice', 'unit'])
            ->whereHas('category', fn ($q) => $q->whereNotNull('restricted_office_id'))
            ->orderBy('code')->get()
            ->reject(fn (Item $item) => in_array($office->id, Office::withDescendantIds([$item->category->restricted_office_id])->all(), true))
            ->values();
    }

    public function create(User $user, Office $office, array $data): ItemRequest
    {
        $item = Item::with('category')->find($data['item_id'] ?? 0);

        if (! $item || ! $this->requestableItems($office)->contains('id', $item->id)) {
            throw new ProcurementException('Choose a standard item that another office procures.');
        }

        $quantity = (float) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new ProcurementException('Enter the quantity requested.');
        }

        $fiscalYear = (int) ($data['fiscal_year'] ?? 0);

        $request = DB::transaction(function () use ($user, $office, $item, $quantity, $fiscalYear, $data) {
            $series = (int) ItemRequest::where('fiscal_year', $fiscalYear)->lockForUpdate()->max('series') + 1;

            return ItemRequest::create([
                'request_no'           => sprintf('IR %d-%04d', $fiscalYear, $series),
                'fiscal_year'          => $fiscalYear,
                'series'               => $series,
                'requesting_office_id' => $office->id,
                'procuring_office_id'  => $item->category->restricted_office_id,
                'item_id'              => $item->id,
                'quantity'             => $quantity,
                'purpose'              => $data['purpose'] ?? null,
                'status'               => ItemRequestStatus::Pending,
                'requested_by'         => $user->id,
            ]);
        });

        foreach ($this->officeUsers($request->procuring_office_id) as $recipient) {
            notify_user($recipient, 'Item request', "{$office->shortName()} requests {$this->qty($quantity)} {$item->name} for FY {$fiscalYear} ({$request->request_no}).", route('procurement.item-requests.index', ['tab' => 'incoming']), 'fas fa-hand-holding');
        }

        return $request;
    }

    public function cancel(ItemRequest $request, User $user): ItemRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $request = ItemRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new ProcurementException("{$request->request_no} is {$request->status->label()}; only a pending request can be cancelled.");
            }

            $request->update(['status' => ItemRequestStatus::Cancelled, 'cancelled_at' => now()]);

            return $request;
        });
    }

    /**
     * The procuring office's projects for the request's item and fiscal year, in PPMPs of the requesting
     * office's region that are not superseded, each with ->distributed (already distributed) and ->room.
     */
    public function projectsFor(ItemRequest $request): Collection
    {
        $region = $request->requestingOffice->effectiveRegion();

        return PpmpItem::with(['ppmp.office', 'pap', 'unit'])
            ->where('item_id', $request->item_id)
            ->whereHas('ppmp', fn ($q) => $q->where('fiscal_year', $request->fiscal_year)->where('region', $region)
                ->where('status', '!=', PpmpStatus::Superseded)
                ->whereIn('office_id', Office::withDescendantIds([$request->procuring_office_id])))
            ->get()
            ->each(function (PpmpItem $project) {
                $project->distributed = (float) PpmpItemDistribution::where('line_uuid', $project->line_uuid)->sum('quantity');
                $project->room = $project->quantity === null ? null : max(0, (float) $project->quantity - $project->distributed);
            });
    }

    /**
     * Approve all, part (0 < quantity < requested) or none (0) of it. What is approved is added to the
     * requesting office's share in $project's distribution, which must stay within the project's quantity.
     */
    public function decide(ItemRequest $request, User $user, float $approved, ?int $projectId, ?string $remarks): ItemRequest
    {
        $request = DB::transaction(function () use ($request, $user, $approved, $projectId, $remarks) {
            $request = ItemRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new ProcurementException("{$request->request_no} is already {$request->status->label()}.");
            }

            if ($approved < 0 || $approved > (float) $request->quantity + 0.0001) {
                throw new ProcurementException("Approve from 0 up to the {$this->qty($request->quantity)} requested.");
            }

            // Declined: the requesting office is told why
            if ($approved <= 0 && blank($remarks)) {
                throw new ProcurementException('State why the request is declined.');
            }

            $project = null;
            if ($approved > 0) {
                $project = $this->projectsFor($request)->firstWhere('id', $projectId);
                if (! $project) {
                    throw new ProcurementException('Choose the PPMP project for this item that the approved quantity goes to.');
                }

                if ($project->room !== null && $approved > $project->room + 0.0001) {
                    throw new ProcurementException("\"{$project->description}\" has room for {$this->qty($project->room)} more (quantity {$this->qty($project->quantity)}, {$this->qty($project->distributed)} distributed). Amend the PPMP to raise its quantity first, or approve less.");
                }

                // The requesting office's share on the project goes up by what is approved
                $share = PpmpItemDistribution::where('line_uuid', $project->line_uuid)->where('office_id', $request->requesting_office_id)->first();
                if ($share) {
                    $share->update(['quantity' => (float) $share->quantity + $approved, 'remarks' => trim(($share->remarks ? $share->remarks . '; ' : '') . $request->request_no)]);
                } else {
                    PpmpItemDistribution::create([
                        'line_uuid' => $project->line_uuid, 'office_id' => $request->requesting_office_id, 'quantity' => $approved,
                        'remarks' => $request->request_no, 'created_by' => $user->id,
                    ]);
                }
            }

            $request->update([
                'status'            => match (true) {
                    $approved <= 0                                       => ItemRequestStatus::Declined,
                    $approved + 0.0001 >= (float) $request->quantity     => ItemRequestStatus::Approved,
                    default                                              => ItemRequestStatus::Partial,
                },
                'approved_quantity' => $approved,
                'decision_remarks'  => $remarks,
                'ppmp_item_id'      => $project?->id,
                'line_uuid'         => $project?->line_uuid,
                'decided_by'        => $user->id,
                'decided_at'        => now(),
            ]);

            return $request;
        });

        $request->load(['item', 'procuringOffice']);
        if ($requester = $request->requester) {
            $by = $request->procuringOffice->shortName();
            $what = "{$this->qty($request->quantity)} {$request->item->name} ({$request->request_no})";
            $message = match ($request->status) {
                ItemRequestStatus::Declined => "{$by} declined your request for {$what}",
                ItemRequestStatus::Partial  => "{$by} approved {$this->qty($request->approved_quantity)} of your request for {$what}",
                default                     => "{$by} approved your request for {$what}",
            };
            notify_user($requester, "Item request {$request->status->label()}", $message . ($remarks ? ": {$remarks}" : '.'), route('procurement.item-requests.index'),
                $request->status === ItemRequestStatus::Declined ? 'fas fa-times-circle' : 'fas fa-hand-holding');
        }

        return $request;
    }

    /** The procuring office's people (its home office is that office or a unit under it) and Super Admin decide. */
    public function canDecide(User $user, ItemRequest $request): bool
    {
        return $user->hasRole('Super Admin')
            || in_array((int) $user->office_id, Office::withDescendantIds([$request->procuring_office_id])->all(), true);
    }

    /** Offices the user decides requests for (its home office when it procures for a category; all for Super Admin). */
    public function procuringOfficeIds(User $user): Collection
    {
        $designated = \App\Models\Procurement\ItemCategory::whereNotNull('restricted_office_id')->pluck('restricted_office_id')->unique();

        if ($user->hasRole('Super Admin')) {
            return $designated->values();
        }

        return $designated->filter(fn ($id) => in_array((int) $user->office_id, Office::withDescendantIds([$id])->all(), true))->values();
    }

    /** Active users of the office (and the units under it), to notify. */
    protected function officeUsers(int $officeId): Collection
    {
        return User::where('is_activated', true)->whereIn('office_id', Office::withDescendantIds([$officeId]))->get();
    }

    protected function qty($q): string
    {
        return rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    }
}
