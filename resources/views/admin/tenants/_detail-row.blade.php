<tr>
    <th scope="row">{{ $label }}</th>
    <td>
        @if(!empty($html))
            {!! $html !!}
        @elseif(isset($value) && trim((string) $value) !== '')
            {{ $value }}
        @else
            <span class="detail-empty">—</span>
        @endif
    </td>
</tr>
