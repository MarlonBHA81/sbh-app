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
}
