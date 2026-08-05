<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessCategoryResource;
use App\Models\BusinessCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class BusinessCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Cache::remember(
            'business:categories',
            now()->addHour(),
            fn () => BusinessCategory::query()
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        );

        return BusinessCategoryResource::collection($categories);
    }
}
