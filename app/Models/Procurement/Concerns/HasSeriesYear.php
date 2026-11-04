<?php

namespace App\Models\Procurement\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Documents numbered in a yearly series (series_year): PRs / JRs and RFQs.
 * A numbered one belongs to its number's year; a draft (no number yet) to the year it was started.
 */
trait HasSeriesYear
{
    public function scopeOfYear(Builder $query, int $year): Builder
    {
        return $query->where(fn ($w) => $w->where($this->qualifyColumn('series_year'), $year)
            ->orWhere(fn ($draft) => $draft->whereNull($this->qualifyColumn('series_year'))
                ->whereBetween($this->qualifyColumn('created_at'), [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()])));
    }

    /** The years a list can be narrowed to, newest first: from the first document's year to this year. */
    public static function yearOptions(Builder $visible): array
    {
        $firstSeries = (clone $visible)->min('series_year');
        $firstStarted = (clone $visible)->min('created_at');
        $first = min(array_filter([(int) now()->year, $firstSeries ? (int) $firstSeries : null, $firstStarted ? Carbon::parse($firstStarted)->year : null]));

        return range((int) now()->year, $first);
    }
}
