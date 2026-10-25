<?php

namespace App\Services\Auth;

use App\Models\User;

class EmailCheckService
{
    /**
     * @return array{available: bool, email: string}
     */
    public function availability(string $email): array
    {
        $normalized = strtolower(trim($email));
        $taken = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->exists();

        return [
            'available' => ! $taken,
            'email' => $normalized,
        ];
    }
}
