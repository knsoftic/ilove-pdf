<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    protected $signature = 'pdf:admin
                            {--email= : E-mail address used to sign in}
                            {--name= : Display name}
                            {--password= : Password (prompted when omitted)}';

    protected $description = 'Create an admin account for the website owner, or reset its password';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('E-mail address');
        $name = $this->option('name') ?: $this->ask('Name', 'Administrator');
        $password = $this->option('password') ?: $this->secret('Password (min. 10 characters)');

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email', 'max:190'],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:10', 'max:200'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()->firstOrNew(['email' => strtolower($email)]);
        $exists = $admin->exists;
        $admin->fill(['name' => $name, 'password' => $password])->save();

        $this->components->info($exists ? "Password of {$admin->email} has been updated." : "Admin {$admin->email} created.");
        $this->line('  Sign in at: '.url(config('pdftoolkit.admin.path', 'admin').'/login'));

        return self::SUCCESS;
    }
}
