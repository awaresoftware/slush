@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';

    $step = 'inline-flex size-8 shrink-0 items-center justify-center rounded-full text-footnote transition-colors duration-150 ease-out-ios';
    $idle = 'text-base-content/55 hover:bg-fill hover:text-base-content';
    $off = 'pointer-events-none text-base-content/25';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav
            role="navigation"
            aria-label="{{ __('Pagination Navigation') }}"
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
            <p class="text-footnote text-base-content/65">
                <span>{!! __('Showing') !!}</span>
                <span class="font-medium text-base-content">{{ $paginator->firstItem() }}</span>
                <span>{!! __('to') !!}</span>
                <span class="font-medium text-base-content">{{ $paginator->lastItem() }}</span>
                <span>{!! __('of') !!}</span>
                <span class="font-medium text-base-content">{{ $paginator->total() }}</span>
                <span>{!! __('results') !!}</span>
            </p>

            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $step }} {{ $off }}" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <x-mary-icon name="o-chevron-left" class="size-4" />
                    </span>
                @else
                    <button
                        type="button"
                        class="{{ $step }} {{ $idle }}"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        aria-label="{{ __('pagination.previous') }}"
                    >
                        <x-mary-icon name="o-chevron-left" class="size-4" />
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        class="{{ $step }} {{ $idle }}"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        aria-label="{{ __('pagination.next') }}"
                    >
                        <x-mary-icon name="o-chevron-right" class="size-4" />
                    </button>
                @else
                    <span class="{{ $step }} {{ $off }}" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <x-mary-icon name="o-chevron-right" class="size-4" />
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
