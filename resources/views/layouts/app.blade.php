<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'FamilyManager' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/app.js') }}?v=4"></script>
</head>
<body class="min-h-screen text-stone-900 antialiased">
    <div class="border-b border-stone-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:flex lg:items-center lg:justify-between lg:gap-8 lg:px-8">
            <div class="flex items-center justify-between gap-4 lg:flex-none">
                <a href="{{ route('dashboard') }}" class="flex w-fit items-center gap-3">
                    <img src="{{ asset('images/familymanager-logo.png') }}" alt="FamilyManager" class="h-10 w-auto max-w-[240px] object-contain sm:h-11 sm:max-w-[300px] lg:h-12 lg:max-w-[360px]">
                </a>
                @auth
                    <button class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-stone-300 text-stone-700 hover:bg-stone-100 lg:hidden" type="button" data-mobile-menu-button aria-controls="mobile-menu" aria-expanded="false">
                        <span class="sr-only">Menü öffnen</span>
                        <svg class="h-5 w-5" data-mobile-menu-open-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="hidden h-5 w-5" data-mobile-menu-close-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endauth
            </div>
            @auth
                <nav id="mobile-menu" class="mt-4 hidden flex-col gap-1 text-sm lg:mt-0 lg:flex lg:flex-1 lg:flex-row lg:flex-wrap lg:items-center lg:justify-end lg:gap-2" data-mobile-menu>
                    <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('dashboard') }}">Dashboard</a>
                    @php($navFamily = auth()->user()->managedFamily())
                    @if($navFamily)
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('calendar') }}">Kalender</a>
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.events.index', $navFamily) }}">Termine</a>
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.document-imports.index', $navFamily) }}">Dokumente / Import</a>
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.index') }}">Einstellungen</a>
                    @else
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.create') }}">Familie erstellen</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full rounded-md px-3 py-2 text-left text-stone-600 hover:bg-stone-100 lg:w-auto" type="submit">Logout</button>
                    </form>
                </nav>
            @endauth
        </div>
    </div>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                {{ $errors->first() }}
            </div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</body>
</html>
