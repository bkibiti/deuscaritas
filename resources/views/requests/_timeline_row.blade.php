{{-- One compact timeline entry: what happened, who did it and when, with any note kept to a line. --}}
<li class="list-group-item py-2">
    <div class="d-flex justify-content-between gap-2">
        <span><strong>{{ $action->label() }}</strong> <span class="text-body-secondary">&middot; {{ $action->user?->name }}</span></span>
        <span class="text-body-secondary text-nowrap">{{ displayTime($action->created_at) }}</span>
    </div>
    @if ($action->note)
        <div class="text-body-secondary" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden" title="{{ $action->note }}">{{ $action->note }}</div>
    @endif
</li>
