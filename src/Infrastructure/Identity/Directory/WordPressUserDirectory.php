<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Identity\Directory;

use Pet\Application\Identity\Directory\UserDirectory;

final class WordPressUserDirectory implements UserDirectory
{
    public function getDisplayName(int $userId): ?string
    {
        if (!function_exists('get_userdata')) {
            return null;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return null;
        }

        return $user->display_name;
    }

    public function getAvatarUrl(int $userId): ?string
    {
        if (!function_exists('get_avatar_url')) {
            return null;
        }

        return get_avatar_url($userId, ['size' => 24]) ?: null;
    }
}
