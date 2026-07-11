<?php

namespace App\Services\Business;

use App\Models\BusinessNeed;
use App\Models\Profile;
use Illuminate\Validation\ValidationException;

class BusinessNeedService
{
    public const MAX_ACTIVE = 10;

    public function __construct(private MatchmakingService $matchmaking) {}

    /**
     * @param  array{kind: string, business_category_id: int, description: string}  $data
     */
    public function create(Profile $profile, array $data): BusinessNeed
    {
        $this->assertUnderActiveCap($profile);

        $need = $profile->businessNeeds()->create([
            'kind' => $data['kind'],
            'business_category_id' => $data['business_category_id'],
            'description' => $data['description'],
            'active' => true,
        ]);

        $this->matchmaking->forget($profile);

        return $need->load('businessCategory');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BusinessNeed $need, array $data): BusinessNeed
    {
        // Re-activating an inactive need must respect the active cap.
        if (array_key_exists('active', $data) && $data['active'] && ! $need->active) {
            $this->assertUnderActiveCap($need->profile);
        }

        $need->fill($data)->save();

        $this->matchmaking->forget($need->profile);

        return $need->refresh()->load('businessCategory');
    }

    public function delete(BusinessNeed $need): void
    {
        $profile = $need->profile;

        $need->delete();

        $this->matchmaking->forget($profile);
    }

    private function assertUnderActiveCap(Profile $profile): void
    {
        if ($profile->businessNeeds()->active()->count() >= self::MAX_ACTIVE) {
            throw ValidationException::withMessages([
                'active' => ['You may have at most '.self::MAX_ACTIVE.' active business needs.'],
            ]);
        }
    }
}
