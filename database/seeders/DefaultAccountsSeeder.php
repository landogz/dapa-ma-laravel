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
                'email' => 'superadmin@yopmail.com',
                'role' => 'super_admin',
            ],
            [
                'name' => 'DAPE-MA Editor',
                'email' => 'editor@yopmail.com',
                'role' => 'editor',
            ],
            [
                'name' => 'DAPE-MA Publisher',
                'email' => 'publisher@yopmail.com',
                'role' => 'publisher',
            ],
            [
                'name' => 'DAPE-MA Analytics Viewer',
                'email' => 'analytics@yopmail.com',
                'role' => 'analytics_viewer',
            ],
            [
                'name' => 'DAPE-MA App User',
                'email' => 'appuser@yopmail.com',
                'role' => 'app_user',
            ],
        ];

        foreach ($accounts as $account) {
            $legacyEmail = preg_replace('/@yopmail\.com$/i', '@dape-ma.local', $account['email']);

            $user = User::query()
                ->whereIn('email', array_values(array_unique([$account['email'], $legacyEmail])))
                ->orderByRaw('CASE WHEN email = ? THEN 0 ELSE 1 END', [$account['email']])
                ->first() ?? new User();

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
