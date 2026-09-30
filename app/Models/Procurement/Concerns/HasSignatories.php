<?php

namespace App\Models\Procurement\Concerns;

use App\Models\Procurement\DocumentSignatory;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSignatories
{
    public function signatories(): MorphMany
    {
        return $this->morphMany(DocumentSignatory::class, 'signable');
    }

    /** Record a signature, freezing the signer's name and designation as of now. */
    public function sign(User $user, string $role, ?string $remarks = null): DocumentSignatory
    {
        return $this->signatories()->create([
            'role'                 => $role,
            'user_id'              => $user->id,
            'name_snapshot'        => trim(preg_replace('/\s+/', ' ', "{$user->fname} {$user->minitial} {$user->lname}")) ?: $user->username,
            'designation_snapshot' => $user->designation,
            'signed_at'            => now(),
            'remarks'              => $remarks,
        ]);
    }

    public function latestSignatory(string $role): ?DocumentSignatory
    {
        return $this->signatories()->where('role', $role)->latest('signed_at')->latest('id')->first();
    }
}
