<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

/** A time PPMPs of a fiscal year may be amended (both dates included), set by the budget officer. */
class PpmpAmendmentPeriod extends Model
{
    protected $fillable = ['fiscal_year', 'name', 'opens_on', 'closes_on', 'created_by'];

    protected function casts(): array
    {
        return ['fiscal_year' => 'integer', 'opens_on' => 'date', 'closes_on' => 'date'];
    }

    /** The period open today for the fiscal year, if any. */
    public static function openFor(int $fiscalYear): ?self
    {
        $today = now()->toDateString();

        return static::where('fiscal_year', $fiscalYear)->whereDate('opens_on', '<=', $today)->whereDate('closes_on', '>=', $today)->first();
    }

    /** "Mar 23 – Apr 23, 2027" */
    public function range(): string
    {
        return $this->opens_on->format('M d') . ' – ' . $this->closes_on->format('M d, Y');
    }
}
