<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Transkripsi &amp; Notulensi Rapat</title>

    {{-- Tema disetel sebelum render pertama supaya tidak ada kedipan putih. --}}
    <script>
        (() => {
            const stored = localStorage.getItem('notulensi.theme');
            const dark = stored === 'dark'
                || (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full">
<a href="#konten"
   class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg
          focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:shadow">
    Lompat ke konten
</a>

<div x-data="notulensi(@js($initialState))" x-cloak class="flex min-h-dvh flex-col">
    @include('partials.header')

    <div id="konten" class="mx-auto grid w-full max-w-[1600px] flex-1 gap-px lg:grid-cols-[340px_minmax(0,1fr)]">
        @include('partials.sidebar')

        <main class="flex min-w-0 flex-col bg-paper">
            @include('partials.tabs')

            <div class="min-h-0 flex-1">
                @include('partials.transcript')
                @include('partials.minutes')
                @include('partials.settings')
            </div>
        </main>
    </div>

    <p class="sr-only" aria-live="polite" x-text="announcement"></p>

    @include('partials.toasts')
</div>
</body>
</html>
