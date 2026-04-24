<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CONTENT_MANAGER = 'content_manager';

    public const ROLE_CUSTOMER = 'customer';

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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->canAccessAdminPanel();
    }

    public static function roleOptions(): array
    {
        return [
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_CONTENT_MANAGER => 'Контент-менеджер',
            self::ROLE_CUSTOMER => 'Покупатель',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isContentManager(): bool
    {
        return $this->role === self::ROLE_CONTENT_MANAGER;
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->isAdmin() || $this->isContentManager();
    }

    public function canManageContentResource(string $resourceKey): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isContentManager()) {
            return false;
        }

        return in_array($resourceKey, [
            'products',
            'brands',
            'categories',
            'news_posts',
            'banners',
            'navigation_menu_items',
        ], true);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function adminActivityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class);
    }
}
