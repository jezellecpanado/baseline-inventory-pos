<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => env('INITIAL_ADMIN_USERNAME', 'admin')],
            [
                'name' => 'System Admin',
                'password' => env('INITIAL_ADMIN_PASSWORD', 'ChangeMe123!'),
                'role' => User::ROLE_ADMIN,
                'active' => true,
            ],
        );
    }
}
