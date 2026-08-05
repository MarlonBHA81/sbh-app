<?php

namespace App\Notifications;

class PostCommented extends ActivityNotification
{
    public function type(): string
    {
        return 'post_commented';
    }
}
