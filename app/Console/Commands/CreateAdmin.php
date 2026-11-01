<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create an administrator using secure interactive prompts or environment values';

    public function handle(): int
    {
        $name = env('ADMIN_NAME') ?: $this->ask('Administrator name');
        $email = env('ADMIN_EMAIL') ?: $this->ask('Administrator email');
        $password = env('ADMIN_PASSWORD') ?: $this->secret('Administrator password (minimum 12 characters)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin']);
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
