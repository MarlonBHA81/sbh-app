<?php

namespace App\Notifications;

class PostReposted extends ActivityNotification
{
    public function type(): string
    {
        return 'post_reposted';
    }
}
