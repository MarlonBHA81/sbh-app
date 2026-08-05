<?php

namespace App\Notifications;

class FollowRequested extends ActivityNotification
{
    public function type(): string
    {
        return 'follow_requested';
    }
}
