<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $ulid = (string) Str::ulid();

        return [
            'ulid' => $ulid,
            'profile_id' => Profile::factory(),
            'type' => Media::TYPE_IMAGE,
            'disk' => 'public',
            'path' => "media/test/{$ulid}.webp",
            'thumb_path' => "media/test/{$ulid}_thumb.webp",
            'width' => 800,
            'height' => 600,
            'size_bytes' => 12345,
            'mime' => 'image/webp',
            'status' => Media::STATUS_READY,
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => Media::TYPE_VIDEO,
            'mime' => 'video/mp4',
            'path' => 'media/test/'.Str::ulid().'.mp4',
            'thumb_path' => null,
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn () => [
            'type' => Media::TYPE_AUDIO,
            'mime' => 'audio/mpeg',
            'path' => 'media/test/'.Str::ulid().'.mp3',
            'thumb_path' => null,
            'width' => null,
            'height' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Media::STATUS_PROCESSING]);
    }
}
