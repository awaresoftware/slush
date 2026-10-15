@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';

    $pageName = $paginator->getPageName();

    /* Built from the same vocabulary as x-ui.icon-button and the "Showing 1 to
       50 of N results" caption it sits beside, so the pager reads as part of
       that line rather than a foreign widget dropped on top of it. */
    $step = 'inline-flex size-8 shrink-0 items-center justify-center rounded-full text-footnote tabular-nums transition-colors duration-150 ease-out-ios';
    $idle = 'text-base-content/55 hover:bg-fill hover:text-base-content';
    $here = 'bg-base-300 font-semibold text-base-content';
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

            <div class="flex flex-wrap items-center justify-end gap-x-0.5 gap-y-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $step }} {{ $off }}" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <x-mary-icon name="o-chevron-left" class="size-4" />
                    </span>
                @else
                    <button
                        type="button"
                        class="{{ $step }} {{ $idle }}"
                        wire:click="previousPage('{{ $pageName }}')"
                        wire:loading.attr="disabled"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        aria-label="{{ __('pagination.previous') }}"
                    >
                        <x-mary-icon name="o-chevron-left" class="size-4" />
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="hidden sm:inline-flex">
                            <span class="{{ $step }} {{ $off }}" aria-hidden="true">{{ $element }}</span>
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $pageName }}-page{{ $page }}" class="hidden sm:inline-flex">
                                @if ($page == $paginator->currentPage())
                                    <span class="{{ $step }} {{ $here }}" aria-current="page">{{ $page }}</span>
                                @else
                                    <button
                                        type="button"
                                        class="{{ $step }} {{ $idle }}"
                                        wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                                        wire:loading.attr="disabled"
                                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                        aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    >{{ $page }}</button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        class="{{ $step }} {{ $idle }}"
                        wire:click="nextPage('{{ $pageName }}')"
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
