<?php

namespace App\Services\Procurement;

use App\Core\Models\Setting;
use App\Enums\Region;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The procurement officials of each region, set on the Signatories & BAC page: the HOPE, the
 * BAC (chair, vice-chair, members), the head of the BAC Secretariat and who signs the RFQs.
 * They act in the system (the BAC Chair recommends and the HOPE approves the APP) and are the
 * default signatories of the documents; a document's own signatories can still be changed on it.
 *
 * The APP roles keep their earlier setting keys, so APPs set up before this page still work.
 */
class OfficialsService
{
    public const MANAGE_PERMISSION = 'manage officials';

    /** Single officials: role => [label, help]. */
    public const ROLES = [
        'hope'             => ['Head of the Procuring Entity (HOPE)', 'Approves the APP; approves awards (AOQ, Notice of Award).'],
        'bac_chair'        => ['BAC Chairperson', 'Recommends the APP; signs the AOQ and BAC resolutions.'],
        'bac_vice_chair'   => ['BAC Vice-Chairperson', 'Signs with the BAC; acts for the Chairperson.'],
        'secretariat_head' => ['Head, BAC Secretariat', 'Prepares the APP.'],
        'rfq_signatory'    => ['RFQ signatory', 'Default "signed by" on new RFQs, e.g. the Supply Officer.'],
    ];

    /** Earlier keys of the APP signatories (AppService::signatoryKey). */
    protected const APP_ROLES = ['hope' => 'approved', 'bac_chair' => 'recommended', 'secretariat_head' => 'prepared'];

    public static function key(Region $region, string $role): string
    {
        return isset(self::APP_ROLES[$role])
            ? AppService::signatoryKey($region, self::APP_ROLES[$role])
            : "officials_{$region->value}_{$role}_user_id";
    }

    public function user(Region $region, string $role): ?User
    {
        $id = Setting::get(self::key($region, $role));

        return $id ? User::find($id) : null;
    }

    /** BAC members (besides the chair and vice-chair), in the order set. */
    public function members(Region $region): Collection
    {
        $ids = json_decode((string) Setting::get("officials_{$region->value}_bac_members", '[]'), true) ?: [];
        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $users->get($id))->filter()->values();
    }

    /** role => ?User for every single role, plus 'bac_members' => Collection. */
    public function all(Region $region): array
    {
        return collect(array_keys(self::ROLES))->mapWithKeys(fn ($role) => [$role => $this->user($region, $role)])->all()
            + ['bac_members' => $this->members($region)];
    }

    public function set(Region $region, array $roles, array $memberIds): void
    {
        foreach (array_keys(self::ROLES) as $role) {
            Setting::set(self::key($region, $role), $roles[$role] ?? null);
        }

        Setting::set("officials_{$region->value}_bac_members", json_encode(array_values(array_unique(array_map('intval', $memberIds)))));
    }

    /** Super Admin: both regions; otherwise the user's own region, with the page's (or the APP's) permission. */
    public function canManage(User $user, Region $region): bool
    {
        $allowed = $user->canAccessPermission(self::MANAGE_PERMISSION) || $user->canAccessPermission(AppService::MANAGE_PERMISSION);

        return $allowed && ($user->hasRole('Super Admin') || ($user->region ?? Region::Lm) === $region);
    }
}
