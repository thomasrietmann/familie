<x-layouts.app title="Login">
    <div class="mx-auto max-w-md">
        <div class="mb-8 text-center">
            <img src="{{ asset('images/familymanager-logo.png') }}" alt="FamilyManager" class="mx-auto h-16 w-auto max-w-full object-contain">
            <h1 class="mt-6 text-3xl font-semibold tracking-tight">Anmelden</h1>
            <p class="mt-2 text-sm text-stone-600">Test-Login: thomas@example.com / password</p>
        </div>
        <form method="POST" action="{{ route('login') }}" class="space-y-5 rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
            @csrf
            <div>
                <label class="text-sm font-medium" for="email">E-Mail</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div>
                <label class="text-sm font-medium" for="password">Passwort</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="password" name="password" type="password" required>
            </div>
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input class="rounded border-stone-300" type="checkbox" name="remember">
                Angemeldet bleiben
            </label>
            <button class="w-full rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700" type="submit">Login</button>
        </form>
    </div>
</x-layouts.app>
