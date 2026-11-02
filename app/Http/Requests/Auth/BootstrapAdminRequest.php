<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\RegisterRequest;
use App\Models\User;

class BootstrapAdminRequest extends RegisterRequest
{
    public function authorize(): bool
    {
        return ! User::query()->where('role', 'super_admin')->exists();
    }
}
