{{--
    Signature blocks in rows, as on the COB forms: $rows = [[[label, ?User|false], ...up to 2], ...].
    First of a row on the left, second on the right. null = not set: a blank line (hint on screen only); false = left out.
--}}
<div class="signatories">
    @foreach ($rows as $row)
        <div class="sig-row">
            @foreach ($row as [$label, $user])
                @if ($user === false)
                    <div></div>
                @else
                    <div class="sig">
                        <div class="label">{{ $label }}</div>
                        @if ($user)
                            <div class="sig-name">{{ $user->fullname }}</div>
                            <div class="sig-pos">{{ $user->designation }}</div>
                        @else
                            <div class="sig-name sig-blank"><span class="no-print">Not set</span></div>
                            <div class="sig-pos">&nbsp;</div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
