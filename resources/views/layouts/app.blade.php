<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'FamilyManager' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/familymanager-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/familymanager-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-stone-900 antialiased">
    <div class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <a href="{{ route('dashboard') }}" class="flex w-fit items-center gap-3">
                <img src="{{ asset('images/familymanager-icon.png') }}" alt="" class="h-10 w-10 rounded-lg object-contain">
                <img src="{{ asset('images/familymanager-logo.png') }}" alt="FamilyManager" class="h-9 w-auto max-w-[220px] object-contain sm:max-w-[280px]">
            </a>
            @auth
                <nav class="flex flex-wrap items-center gap-2 text-sm">
                    <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.index') }}">Familie / Eltern</a>
                    @php($navFamily = auth()->user()->activeFamilies()->first())
                    @if($navFamily)
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.events.index', $navFamily) }}">Termine</a>
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.children.index', $navFamily) }}">Kinder</a>
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.document-imports.index', $navFamily) }}">Dokumente / Import</a>
                        <a class="rounded-md px-3 py-2 hover:bg-stone-100" href="{{ route('families.public-link.show', $navFamily) }}">Secret Link</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md px-3 py-2 text-stone-600 hover:bg-stone-100" type="submit">Logout</button>
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
