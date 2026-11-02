<?php

namespace App\Support\Auth;

final class LoginOtp
{
    public static function required(): bool
    {
        return ! app()->environment('local');
    }
}
