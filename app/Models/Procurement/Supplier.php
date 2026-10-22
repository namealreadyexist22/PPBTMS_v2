<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier/dealer in the directory, picked on an AOQ so its details are not typed again.
 * The AOQ keeps its own copy of the details, so changing them here does not change abstracts.
 */
class Supplier extends Model
{
    public const MANAGE_PERMISSION = 'manage suppliers';

    protected $fillable = ['name', 'address', 'tin', 'contact', 'philgeps_no', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Its quotations on abstracts. */
    public function aoqSuppliers(): HasMany
    {
        return $this->hasMany(AoqSupplier::class);
    }
}
