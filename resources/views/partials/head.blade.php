@php
    $theme = \App\Enums\Theme::forUser(auth()->user());
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

<title>{{ $title ?? config('app.name') }}</title>

@if ($theme->isSystem())
    <meta name="theme-color" content="{{ \App\Enums\Theme::Porcelain->canvas() }}" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ \App\Enums\Theme::Graphite->canvas() }}" media="(prefers-color-scheme: dark)">
@else
    <meta name="theme-color" content="{{ $theme->canvas() }}">
@endif

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">

@vite(['resources/css/app.css', 'resources/js/app.js'])
