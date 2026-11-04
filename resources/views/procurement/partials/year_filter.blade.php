{{-- A list's year filter: this year unless another is picked ("all" = every year). Expects $years (newest first), $year (null = all). --}}
<select name="year" class="form-select form-select-sm" style="width: 130px;" aria-label="Year">
    <option value="all" @selected($year === null)>All years</option>
    @foreach ($years as $y)
        <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
    @endforeach
</select>
