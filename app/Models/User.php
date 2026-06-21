<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Experience;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage as StorageFacade;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'bio', 'designation', 'website', 'city', 'country', 'address', 'joining_date', 'skills', 'password', 'is_active', 'avatar', 'cover_photo', 'setup_token', 'setup_completed_at', 'setup_token_sent_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'is_active' => 'boolean',
            'joining_date' => 'date',
            'setup_completed_at' => 'datetime',
            'setup_token_sent_at' => 'datetime',
        ];
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->avatar) {
                StorageFacade::disk('public')->delete($user->avatar);
            }
            if ($user->cover_photo) {
                StorageFacade::disk('public')->delete($user->cover_photo);
            }
        });
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_photo ? Storage::url($this->cover_photo) : null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
