<?php

namespace App\Services\Posts\Handlers;

use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostTypeHandler;
use Illuminate\Support\Carbon;

class JobHandler implements PostTypeHandler
{
    public function eagerLoad(): array
    {
        return ['jobListing'];
    }

    public function createSatellite(Post $post, array $payload): void
    {
        $post->jobListing()->create($this->attributes($payload));
    }

    public function updateSatellite(Post $post, array $payload): void
    {
        $job = $post->jobListing;

        if ($job === null) {
            $this->createSatellite($post, $payload);
        } else {
            $job->update($this->attributes($payload));
        }

        $post->load('jobListing');
    }

    public function present(Post $post, ?Profile $viewer): array
    {
        $job = $post->jobListing;

        if ($job === null) {
            return [];
        }

        return [
            'job' => [
                'title' => $job->title,
                'company' => $job->company,
                'location' => $job->location,
                'employment_type' => $job->employment_type,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'currency' => $job->currency,
                'apply_url' => $job->apply_url,
                'expires_at' => $job->expires_at?->toISOString(),
                'is_expired' => $job->isExpired(),
            ],
        ];
    }

    private function attributes(array $payload): array
    {
        return [
            'title' => $payload['title'],
            'company' => $payload['company'] ?? null,
            'location' => $payload['location'] ?? null,
            'employment_type' => $payload['employment_type'],
            'salary_min' => $payload['salary_min'] ?? null,
            'salary_max' => $payload['salary_max'] ?? null,
            'currency' => isset($payload['currency']) ? strtoupper($payload['currency']) : 'ZAR',
            'apply_url' => $payload['apply_url'] ?? null,
            'expires_at' => isset($payload['expires_at']) ? Carbon::parse($payload['expires_at']) : null,
        ];
    }
}
