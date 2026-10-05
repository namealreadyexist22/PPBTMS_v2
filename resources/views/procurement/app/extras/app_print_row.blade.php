<tr>
    <td>{{ $line->project_title }}</td>
    <td class="c">{{ $line->end_user }}</td>
    <td>{{ $line->description }}</td>
    <td class="c">{{ $line->procurementMode->name }}</td>
    <td class="c">{{ $line->is_cse ? 'N/A' : ($line->early_procurement ? 'Yes' : 'No') }}</td>
    <td class="c">{{ $line->bid_criteria }}</td>
    <td class="c">{{ $line->proc_start->format('n/Y') }}</td>
    <td class="c">{{ $line->proc_end->format('n/Y') }}</td>
    <td>{{ $line->fundSource->name }}</td>
    <td class="r">₱{{ number_format((float) $line->estimated_budget, 2) }}</td>
    <td class="c">{{ $line->procurement_strategy }}</td>
    <td>{{ $line->remarks }}</td>
</tr>
