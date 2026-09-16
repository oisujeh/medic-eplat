<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoStaffSeeder extends Seeder
{
    /**
     * Seed a handful of named staff so queues/assignment/clinical have realistic
     * personnel to work with. All share the password "Password123!".
     *
     * @var array<int, array{name: string, username: string, role: string}>
     */
    protected array $staff = [
        ['name' => 'Nurse Joy', 'username' => 'nursejoy', 'role' => 'nurse'],
        ['name' => 'Nurse Ada', 'username' => 'nurseada', 'role' => 'nurse'],
        ['name' => 'Dr. House', 'username' => 'drhouse', 'role' => 'physician'],
        ['name' => 'Dr. Grey', 'username' => 'drgrey', 'role' => 'physician'],
        ['name' => 'Pharm. Bello', 'username' => 'pharmbello', 'role' => 'pharmacy-staff'],
        ['name' => 'Lab. Musa', 'username' => 'labmusa', 'role' => 'laboratory-staff'],
    ];

    public function run(): void
    {
        foreach ($this->staff as $person) {
            $user = User::updateOrCreate(
                ['username' => $person['username']],
                [
                    'name' => $person['name'],
                    'email' => $person['username'].'@medic-eplat.test',
                    'password' => Hash::make('Password123!'),
                    'email_verified_at' => now(),
                ],
            );

            $roleId = Role::where('slug', $person['role'])->value('id');

            if ($roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }
        }
    }
}
