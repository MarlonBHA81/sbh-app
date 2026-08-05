<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $this->activeProfile($request);

        $notifications = $this->query($request, $profile)
            ->latest()
            ->cursorPaginate(self::PER_PAGE);

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);

        $count = $this->query($request, $profile)->whereNull('read_at')->count();

        return response()->json(['count' => $count]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);

        $this->query($request, $profile)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['count' => 0]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $profile = $this->activeProfile($request);

        $notification = $this->query($request, $profile)->whereKey($id)->firstOrFail();

        $notification->markAsRead();

        return response()->json(['id' => $notification->id, 'read_at' => $notification->read_at?->toISOString()]);
    }

    private function query(Request $request, Profile $profile)
    {
        return $request->user()
            ->notifications()
            ->where('data->target_profile_ulid', $profile->ulid);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
