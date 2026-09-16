<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed a Super Administrator account.
     *
     * Credentials can be overridden with the SUPER_ADMIN_* environment
     * variables. The account is created (or updated) idempotently, so this
     * seeder is safe to run repeatedly.
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@medic-eplat.test');
        $username = env('SUPER_ADMIN_USERNAME', 'superadmin');
        $password = env('SUPER_ADMIN_PASSWORD', 'ChangeMe123!');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Administrator'),
                'username' => $username,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $role = Role::where('slug', 'super-administrator')->first();

        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->command?->info('Super Administrator ready:');
        $this->command?->line("  email:    {$email}");
        $this->command?->line("  username: {$username}");
        $this->command?->line("  password: {$password}");
        $this->command?->warn('  Change this password after first login.');
    }
}
