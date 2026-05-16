<x-layouts.app title="Registrieren">
    <div class="mx-auto max-w-md">
        <div class="mb-8 text-center">
            <img src="{{ asset('images/familymanager-logo.png') }}" alt="FamilyManager" class="mx-auto h-16 w-auto max-w-full object-contain">
            <h1 class="mt-6 text-3xl font-semibold tracking-tight">Registrieren</h1>
            <p class="mt-2 text-sm text-stone-600">Ein Login erstellt und verwaltet genau eine Familie.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5 rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
            @csrf
            <div>
                <label class="text-sm font-medium" for="name">Name</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="name" name="name" value="{{ old('name') }}" required autofocus>
            </div>
            <div>
                <label class="text-sm font-medium" for="email">E-Mail</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="email" name="email" type="email" value="{{ old('email') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium" for="family_name">Familienname</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="family_name" name="family_name" value="{{ old('family_name') }}" placeholder="Familie Rietmann" required>
            </div>
            <div>
                <label class="text-sm font-medium" for="password">Passwort</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="password" name="password" type="password" required>
            </div>
            <div>
                <label class="text-sm font-medium" for="password_confirmation">Passwort bestätigen</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="password_confirmation" name="password_confirmation" type="password" required>
            </div>
            <button class="w-full rounded-md bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-700" type="submit">Account erstellen</button>
        </form>

        <p class="mt-5 text-center text-sm text-stone-600">
            Bereits registriert?
            <a class="font-medium text-stone-900 hover:underline" href="{{ route('login') }}">Anmelden</a>
        </p>
    </div>
</x-layouts.app>
