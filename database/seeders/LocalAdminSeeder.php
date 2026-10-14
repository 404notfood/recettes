<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal() || User::where('email', 'admin@idee.test')->exists()) {
            return;
        }

        $password = Str::password(24);
        $admin = new User;
        $admin->forceFill(['name' => 'Administration Cuistot Club', 'email' => 'admin@idee.test', 'password' => $password, 'email_verified_at' => now(), 'is_admin' => true])->save();
        Storage::disk('local')->put('local-admin.txt', "Accès LOCAL uniquement — https://idee.test/login\nEmail : admin@idee.test\nMot de passe : ".$password."\nCe compte est créé seulement en environnement local.\n");
    }
}
