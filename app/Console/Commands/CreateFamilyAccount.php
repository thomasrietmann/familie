<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateFamilyAccount extends Command
{
    protected $signature = 'family:create-account';

    protected $description = 'Familie und Owner mit verdeckter Passwortabfrage anlegen';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Dieser Befehl benötigt eine interaktive Passwortabfrage.');

            return self::FAILURE;
        }
        $data = [
            'name' => $this->ask('Name'),
            'email' => mb_strtolower(trim((string) $this->ask('E-Mail'))),
            'family_name' => $this->ask('Familienname'),
            'password' => $this->secret('Passwort (mindestens 12 Zeichen, Gross-/Kleinbuchstaben und Zahl)'),
            'password_confirmation' => $this->secret('Passwort bestätigen'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'family_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error('Angaben ungültig, E-Mail bereits vorhanden oder Passwortanforderungen nicht erfüllt.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($data): void {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $family = Family::create(['name' => $data['family_name'], 'owner_user_id' => $user->id]);
            $family->users()->attach($user, ['role' => 'owner', 'status' => 'active', 'accepted_at' => now()]);
        });
        $this->info('Familienaccount wurde erstellt.');

        return self::SUCCESS;
    }
}
