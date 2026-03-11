<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultAccountsSeeder extends Seeder
{
    /**
     * Seed default accounts for all application roles.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'DAPE-MA Super Admin',
                'email' => 'superadmin@dape-ma.local',
                'role' => 'super_admin',
            ],
            [
                'name' => 'DAPE-MA Editor',
                'email' => 'editor@dape-ma.local',
                'role' => 'editor',
            ],
            [
                'name' => 'DAPE-MA Publisher',
                'email' => 'publisher@dape-ma.local',
                'role' => 'publisher',
            ],
            [
                'name' => 'DAPE-MA Analytics Viewer',
                'email' => 'analytics@dape-ma.local',
                'role' => 'analytics_viewer',
            ],
            [
                'name' => 'DAPE-MA App User',
                'email' => 'appuser@dape-ma.local',
                'role' => 'app_user',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew([
                'email' => $account['email'],
            ]);

            $user->forceFill([
                'name' => $account['name'],
                'email' => $account['email'],
                'role' => $account['role'],
                'password' => 'password',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ])->save();
        }
    }
}
