<?php

namespace App\Services\Procurement;

use App\Core\Models\Setting;
use App\Models\Procurement\Office;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who signs the Corporate Operating Budget printouts. Agency-wide (set on Signatories & BAC):
 * the endorsers of the Breakdown, the evaluators, who recommends and who approves.
 * Per department (set on Budget Allocation): who submits its Budget Proposal; the department
 * head when no one is set.
 */
class CobSignatoryService
{
    /** role => [label on the form, help] */
    public const ROLES = [
        'endorsed_1'    => ['Endorsed by (1)', 'Breakdown per Office / Department, e.g. the Budget Officer.'],
        'endorsed_2'    => ['Endorsed by (2)', 'Breakdown per Office / Department, e.g. the Finance Manager.'],
        'evaluated_1'   => ['Evaluated by (1)', 'Budget Proposal of each department.'],
        'evaluated_2'   => ['Evaluated by (2)', 'Budget Proposal of each department.'],
        'recommended'   => ['Recommended by', 'Breakdown and Budget Proposals, e.g. the Administrator.'],
        'approved'      => ['Approved by', 'By the authority of the Sugar Board.'],
    ];

    public function key(string $role): string
    {
        return "cob_{$role}_user_id";
    }

    public function user(string $role): ?User
    {
        $id = Setting::get($this->key($role));

        return $id ? User::find($id) : null;
    }

    /** role => ?User */
    public function all(): array
    {
        return collect(array_keys(self::ROLES))->mapWithKeys(fn ($role) => [$role => $this->user($role)])->all();
    }

    public function set(array $roles): void
    {
        foreach (array_keys(self::ROLES) as $role) {
            Setting::set($this->key($role), $roles[$role] ?? null);
        }
    }

    /** Ids set to submit the department's proposal (in order), none = its head. */
    public function submitterIds(Office $department): array
    {
        return json_decode((string) Setting::get("cob_submitted_{$department->id}", '[]'), true) ?: [];
    }

    /** Who submits the department's Budget Proposal: those set, else the department head. */
    public function submitters(Office $department): Collection
    {
        $ids = $this->submitterIds($department);

        if (! $ids) {
            return collect([$department->head])->filter()->values();
        }

        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $users->get($id))->filter()->values();
    }

    public function setSubmitters(Office $department, array $userIds): void
    {
        Setting::set("cob_submitted_{$department->id}", json_encode(array_values(array_unique(array_map('intval', $userIds)))));
    }
}
