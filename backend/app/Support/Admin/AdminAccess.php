<?php

namespace App\Support\Admin;

use App\Models\User;

class AdminAccess
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function canUseAdminOnlyResource(): bool
    {
        return self::user()?->isAdmin() ?? false;
    }

    public static function canManageContentResource(string $resourceKey): bool
    {
        return self::user()?->canManageContentResource($resourceKey) ?? false;
    }
}
