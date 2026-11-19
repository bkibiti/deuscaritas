{{-- One item line of the request form. Expects $index, $line (item_id, quantity) and $itemGroups. --}}
<tr>
    <td class="request-item-sn text-center"></td>
    <td>
        <select name="items[{{ $index }}][item_id]" class="form-select request-item-select" required>
            <option value="">Choose an item</option>
            @foreach ($itemGroups as $groupName => $groupItems)
                <optgroup label="{{ $groupName }}">
                    @foreach ($groupItems as $item)
                        <option value="{{ $item->id }}" data-unit="{{ $item->unit?->abbreviation }}" data-available="{{ (float) $item->available }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->name }} ({{ rtrim(rtrim(number_format((float) $item->available, 3, '.', ''), '0'), '.') ?: '0' }} available)</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </td>
    <td class="request-item-unit text-body-secondary"></td>
    <td class="request-item-available text-end text-body-secondary"></td>
    <td><input type="number" name="items[{{ $index }}][quantity]" class="form-control request-item-quantity" min="0" step="any" required value="{{ $line['quantity'] ?? '' }}"></td>
    <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm request-item-remove" title="Remove this line"><i class="bi bi-x-lg"></i></button></td>
</tr>
