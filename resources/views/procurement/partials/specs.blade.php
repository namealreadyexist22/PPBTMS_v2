{{-- Specifications: a two-column table when lines are "Label: value" (a line without a label continues the row
     above it), else the text as written. Expects $text; $print (optional): plain black borders for the printed forms. --}}
@php $print ??= false; @endphp
@if (\App\Support\Specs::isTable($text ?? ''))
    @php
        $cell = $print ? 'border: 1px solid #000; padding: 1px 4px; vertical-align: top; font-style: normal;' : 'vertical-align: top;';
    @endphp
    <table class="{{ $print ? 'specs-table' : 'table table-sm table-bordered mb-0 small' }}" style="{{ $print ? 'width: 100%; border-collapse: collapse; margin-top: 4px;' : '' }}">
        <tbody>
            @foreach (\App\Support\Specs::table($text) as [$label, $value])
                <tr>
                    @if ($label === '')
                        <td colspan="2" style="{{ $cell }} white-space: pre-line;">{{ $value }}</td>
                    @else
                        <td style="{{ $cell }} width: 34%;" class="{{ $print ? '' : 'text-muted fw-semibold' }}">{{ $label }}</td>
                        <td style="{{ $cell }} white-space: pre-line;">{{ $value }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif (filled($text ?? null))
    <div style="white-space: pre-line;">{{ $text }}</div>
@endif
