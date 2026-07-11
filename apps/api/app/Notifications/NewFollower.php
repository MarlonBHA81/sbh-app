<?php

namespace App\Notifications;

class NewFollower extends ActivityNotification
{
    public function type(): string
    {
        return 'new_follower';
    }
}
