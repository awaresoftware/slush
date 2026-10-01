@props(['rows' => 5])

<div class="space-y-px" aria-hidden="true">
    @for ($i = 0; $i < $rows; $i++)
        <div class="flex items-center gap-3 py-2">
            <div class="skeleton-block size-10 shrink-0 rounded-art"></div>
            <div class="min-w-0 flex-1 space-y-1.5">
                <div class="skeleton-block h-3 rounded-full" style="width: {{ [55, 40, 65, 48, 60][$i % 5] }}%"></div>
                <div class="skeleton-block h-2.5 rounded-full" style="width: {{ [30, 42, 25, 38, 33][$i % 5] }}%"></div>
            </div>
            <div class="skeleton-block h-2.5 w-8 shrink-0 rounded-full"></div>
        </div>
    @endfor
</div>
