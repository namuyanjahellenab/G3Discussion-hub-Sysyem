<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new administrator account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Create a new administrator account.');

        $fullName = $this->ask('Full name');
        $email = $this->ask('Email address');
        $password = $this->ask('Password (min 8 chars, upper/lower/number/symbol)');
        $passwordConfirm = $this->ask('Confirm password');

        $validator = Validator::make([
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
        ], [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:User,Email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('- ' . $error);
            }
            return 1;
        }

        if ($password !== $passwordConfirm) {
            $this->error('Passwords do not match.');
            return 1;
        }

        $user = User::create([
            'UserName' => $fullName,
            'Handle' => strtolower(str_replace(' ', '', $fullName)),
            'Email' => $email,
            'PasswordHash' => Hash::make($password),
            'Role' => 'Administrator',
            'Status' => 'Active',
            'RulesAccepted' => true,
            'LastActive' => now(),
        ]);

        $this->info("Administrator account created successfully!");
        $this->table(['UserID', 'Name', 'Email', 'Role'], [
            [$user->UserID, $user->UserName, $user->Email, $user->Role],
        ]);

        return 0;
    }
}