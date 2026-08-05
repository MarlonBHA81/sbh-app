<?php

namespace App\Notifications;

class CommentReplied extends ActivityNotification
{
    public function type(): string
    {
        return 'comment_replied';
    }
}
