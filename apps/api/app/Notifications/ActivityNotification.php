<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base for all in-app activity notifications.
 *
 * Notifiable is always the User; the specific profile a notification belongs
 * to is carried in the `target_profile_ulid` payload so the notifications
 * endpoints can filter to the viewer's active profile.
 *
 * Channels: database + broadcast always; webpush is added dynamically when
 * the recipient has push subscriptions AND VAPID keys are configured.
 */
abstract class ActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Profile $actor,
        public Profile $recipient,
        public ?Post $post = null,
        public ?Comment $comment = null,
        public ?string $preview = null,
    ) {}

    /** snake_case type string per the frontend contract. */
    abstract public function type(): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->shouldWebPush($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_filter([
            'type' => $this->type(),
            'actor' => [
                'ulid' => $this->actor->ulid,
                'handle' => $this->actor->handle,
                'name' => $this->actor->name,
                'avatar_url' => $this->actor->avatarUrl(),
            ],
            'post_ulid' => $this->post?->ulid,
            'comment_ulid' => $this->comment?->ulid,
            'preview' => $this->preview,
            'target_profile_ulid' => $this->recipient->ulid,
        ], fn ($value) => $value !== null);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Broadcast onto the recipient profile's private channel rather than the
     * default per-user channel, since a user may own several profiles.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('profile.'.$this->recipient->ulid)];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->actor->name)
            ->body($this->preview ?? $this->type())
            ->data(['id' => $notification->id] + $this->toArray($notifiable));
    }

    private function shouldWebPush(object $notifiable): bool
    {
        return ! empty(config('webpush.vapid.public_key'))
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists();
    }
}
