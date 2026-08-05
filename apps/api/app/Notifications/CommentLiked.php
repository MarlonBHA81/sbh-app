<?php

namespace App\Notifications;

class CommentLiked extends ActivityNotification
{
    public function type(): string
    {
        return 'comment_liked';
    }
}
