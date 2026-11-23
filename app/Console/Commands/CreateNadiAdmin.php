<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateNadiAdmin extends Command
{
    protected $signature = 'nadi:admin-create';

    protected $description = 'Create a NADI administrator account';

    public function handle(): int
    {
        $name = trim($this->ask('Name'));

        $username = strtolower(trim($this->ask('Username')));

        $email = strtolower(trim($this->ask('Email')));

        $password = $this->secret('Password');

        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => [
                    'required',
                    'string',
                    'max:50',
                    'unique:users,username',
                ],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],
                'password' => [
                    'required',
                    'string',
                    'min:12',
                    'confirmed',
                ],
            ]
        );

        if ($validator->fails())
        {
            $this->error('Validation failed.');

            foreach ($validator->errors()->all() as $error)
            {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $name],
                ['Username', $username],
                ['Email', $email],
            ]
        );

        if (! $this->confirm('Create this administrator?', false))
        {
            $this->warn('Administrator creation cancelled.');

            return self::SUCCESS;
        }

        try
        {
            $user = DB::transaction(function () use (
                $name,
                $username,
                $email,
                $password
            ) {
                return User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                ]);
            });
        }
        catch (Throwable $e)
        {
            $this->error('Failed to create administrator.');

            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        $this->info('Administrator created successfully.');

        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Username', $user->username],
                ['Email', $user->email],
            ]
        );

        return self::SUCCESS;
    }
}
