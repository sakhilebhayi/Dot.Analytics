<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Team $currentTeam
 * @property bool $is_platform_operator
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'password' => 'hashed',
            'is_platform_operator' => 'boolean',
        ];
    }

    /**
     * Team owner, or a member whose role on the team is 'admin'. Plain members
     * (editor etc.) are read-only across the intelligence layer.
     */
    public function administersTeam(?Team $team): bool
    {
        if (! $team) {
            return false;
        }

        if ($team->user_id === $this->id) {
            return true;
        }

        return $team->users()
            ->where('user_id', $this->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Platform operators run the Dot.Analytics deployment itself (global feature
     * flags). This is deliberately unrelated to any team role: owning or
     * administering a team never makes a user an operator. Not mass assignable.
     */
    public function isPlatformOperator(): bool
    {
        return $this->is_platform_operator === true;
    }
}
