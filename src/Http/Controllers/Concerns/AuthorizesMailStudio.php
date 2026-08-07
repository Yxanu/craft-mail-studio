<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers\Concerns;

use function CraftCms\Cms\currentUser;

trait AuthorizesMailStudio
{
    private function authorizeMailStudio(string $permission): void
    {
        $user = currentUser();
        abort_unless($user && ($user->isAdmin() || $user->can($permission)), 403);
    }
}
