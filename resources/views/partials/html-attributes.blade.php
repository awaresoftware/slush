@php
    $theme = \App\Enums\Theme::forUser(auth()->user());
    $accent = \App\Enums\Accent::forUser(auth()->user());
@endphp
lang="{{ str_replace('_', '-', app()->getLocale()) }}" @unless ($theme->isSystem()) data-theme="{{ $theme->value }}" @endunless data-accent="{{ $accent->value }}"
