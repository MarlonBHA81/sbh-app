<?php

namespace App\Notifications;

class PostLiked extends ActivityNotification
{
    public function type(): string
    {
        return 'post_liked';
    }
}
