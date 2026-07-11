<?php

namespace App\Http\Requests;

use App\Models\Media;
use App\Services\UploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = (string) $this->input('type');

        $maxBytes = match ($type) {
            Media::TYPE_VIDEO => ((int) config('media.max_video_mb')) * 1024 * 1024,
            Media::TYPE_AUDIO => ((int) config('media.max_audio_mb')) * 1024 * 1024,
            default => ((int) config('media.max_video_mb')) * 1024 * 1024,
        };

        $mimes = UploadService::MIMES[$type] ?? array_merge(...array_values(UploadService::MIMES));

        return [
            'filename' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([Media::TYPE_VIDEO, Media::TYPE_AUDIO])],
            'mime' => ['required', 'string', Rule::in($mimes)],
            'size_bytes' => ['required', 'integer', 'min:1', "max:{$maxBytes}"],
        ];
    }
}
