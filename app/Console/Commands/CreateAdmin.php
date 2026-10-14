<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email : Adresse du nouveau compte} {--name=Administration Cuistot Club : Nom affiché}';

    protected $description = 'Créer un administrateur avec un mot de passe saisi de manière confidentielle';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $validator = Validator::make(compact('email', 'name'), ['email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'string', 'max:255']]);
        if ($validator->fails()) {
            $this->error('Adresse invalide, compte déjà existant ou nom invalide. Aucun compte modifié.');

            return self::FAILURE;
        }

        $password = $this->secret('Mot de passe (12 caractères minimum)', false);
        $confirmation = $this->secret('Confirmer le mot de passe', false);
        if (! is_string($password) || mb_strlen($password) < 12 || mb_strlen($password) > 255 || $password !== $confirmation) {
            $this->error('Mot de passe invalide ou confirmation différente. Aucun compte créé.');

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password, 'is_admin' => true, 'email_verified_at' => now()])->save();
        $this->info('Compte administrateur créé : '.$email);

        return self::SUCCESS;
    }
}
