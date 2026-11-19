{{-- One item line of a Stock In form. Expects $index, $line (item_id, quantity, unit_cost) and $itemGroups. --}}
<tr>
    <td class="si-sn text-center"></td>
    <td>
        <select name="items[{{ $index }}][item_id]" class="form-select si-item" required>
            <option value="">Choose an item</option>
            @foreach ($itemGroups as $groupName => $groupItems)
                <optgroup label="{{ $groupName }}">
                    @foreach ($groupItems as $item)
                        <option value="{{ $item->id }}" data-unit="{{ $item->unit?->abbreviation }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </td>
    <td class="si-unit text-body-secondary"></td>
    <td><input type="number" name="items[{{ $index }}][quantity]" class="form-control si-qty" min="0" step="any" required value="{{ $line['quantity'] ?? '' }}"></td>
    <td><input type="number" name="items[{{ $index }}][unit_cost]" class="form-control si-cost" min="0" step="any" required value="{{ $line['unit_cost'] ?? '' }}"></td>
    <td class="si-amount text-end"></td>
    <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm si-remove" title="Remove this line"><i class="bi bi-x-lg"></i></button></td>
</tr>
