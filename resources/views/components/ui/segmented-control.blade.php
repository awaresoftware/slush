@props([
    'options' => [],
    'model' => 'activeTab',
    'current' => null,
    'label' => null,
])

@php
    $values = array_keys($options);
    $count = count($values);
    $index = (int) max(array_search($current, $values, true), 0);
    $next = $values[($index + 1) % $count];
    $previous = $values[($index - 1 + $count) % $count];
@endphp

{{-- iOS segmented control. The selected value lives on the Livewire component,
     so a tab switch round-trips and the panel the server renders is the one on
     screen — an Alpine-only tab state leaves the two out of step and leaves the
     panel unpatched, because Livewire does not morph inside <template x-if>. --}}
<div
    class="relative inline-grid rounded-field bg-base-300 p-1"
    style="grid-template-columns: repeat({{ $count }}, minmax(0, 1fr))"
    role="tablist"
    @if ($label) aria-label="{{ $label }}" @endif
    wire:keydown.arrow-right.prevent="$set('{{ $model }}', '{{ $next }}')"
    wire:keydown.arrow-left.prevent="$set('{{ $model }}', '{{ $previous }}')"
>
    <span
        aria-hidden="true"
        class="pointer-events-none absolute inset-y-1 left-1 rounded-selector bg-base-100 shadow-1 transition-transform duration-200 ease-out-ios"
        style="width: calc((100% - 0.5rem) / {{ $count }}); transform: translateX({{ $index * 100 }}%)"
    ></span>

    @foreach ($options as $value => $text)
        @php $isCurrent = $value === $current; @endphp

        <button
            type="button"
            role="tab"
            wire:key="seg-{{ $value }}"
            wire:click="$set('{{ $model }}', '{{ $value }}')"
            aria-selected="{{ $isCurrent ? 'true' : 'false' }}"
            @if ($isCurrent) aria-current="true" @endif
            tabindex="{{ $isCurrent ? 0 : -1 }}"
            class="relative z-10 rounded-selector px-4 py-1.5 text-subhead font-medium whitespace-nowrap transition-colors duration-150 ease-out-ios {{ $isCurrent ? 'text-base-content' : 'text-base-content/65 hover:text-base-content' }}"
        >{{ $text }}</button>
    @endforeach
</div>
