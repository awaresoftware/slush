<!DOCTYPE html>
<html @include('partials.html-attributes')>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen flex items-center justify-center font-sans antialiased bg-base-200">
        <div class="w-full max-w-[400px] px-4 py-10">
            <div class="mb-8 flex justify-center">
                <x-ui.wordmark class="scale-110" />
            </div>

            <div class="rounded-box border border-separator bg-base-100 p-8 shadow-1">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
