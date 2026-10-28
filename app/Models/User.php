<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Password\PasswordHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'nickname',
        'pronouns',
        'birthday',
        'persona',
        'personas',
        'interests',
        'onboarding_completed_at',
        'accepted_terms_of_use_at',
        'accepted_privacy_policy_at',
        'accepted_privacy_notice_at',
        'email',
        'password',
        'must_change_password',
        'password_changed_at',
        'role',
        'fcm_token',
        'fcm_platform',
        'profile_image_url',
        'about',
        'journal_custom_feelings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birthday' => 'date',
            'interests' => 'array',
            'personas' => 'array',
            'journal_custom_feelings' => 'array',
            'onboarding_completed_at' => 'datetime',
            'accepted_terms_of_use_at' => 'datetime',
            'accepted_privacy_policy_at' => 'datetime',
            'accepted_privacy_notice_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    public function careCheckResults(): HasMany
    {
        return $this->hasMany(CareCheckResult::class);
    }
}
