<?php

namespace App\Notifications;

class Mentioned extends ActivityNotification
{
    public function type(): string
    {
        return 'mentioned';
    }
}
