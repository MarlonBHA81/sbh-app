<?php

namespace App\Notifications;

class FollowAccepted extends ActivityNotification
{
    public function type(): string
    {
        return 'follow_accepted';
    }
}
