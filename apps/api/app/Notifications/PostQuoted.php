<?php

namespace App\Notifications;

class PostQuoted extends ActivityNotification
{
    public function type(): string
    {
        return 'post_quoted';
    }
}
