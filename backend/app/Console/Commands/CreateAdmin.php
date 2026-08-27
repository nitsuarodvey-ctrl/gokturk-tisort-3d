<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email}';

    protected $description = 'Create or rotate a GUB admin account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Geçerli bir e-posta adresi girin.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('En az 14 karakterlik admin şifresi');
        if (strlen($password) < 14 || strlen($password) > 128) {
            $this->error('Şifre 14–128 karakter olmalıdır.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'GUB Admin',
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );

        $this->info('Admin hesabı hazırlandı.');

        return self::SUCCESS;
    }
}
