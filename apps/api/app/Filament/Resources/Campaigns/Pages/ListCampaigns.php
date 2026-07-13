<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Post;
use App\Services\Ads\CampaignService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Launch a campaign for any published public post straight from
            // the admin — same rules as the in-app Ad Center (metrics-first,
            // no budget), attributed to the post's own profile.
            Action::make('launch')
                ->label('Launch campaign')
                ->form([
                    TextInput::make('post_ulid')
                        ->label('Post ULID')
                        ->helperText('From the post URL: /p/{ulid}. Must be a published, public post.')
                        ->required()
                        ->maxLength(26),
                    TextInput::make('duration_days')
                        ->label('Duration (days)')
                        ->numeric()
                        ->default(7)
                        ->minValue(1)
                        ->maxValue((int) config('ads.max_duration_days'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $post = Post::query()
                        ->where('ulid', trim((string) $data['post_ulid']))
                        ->first();

                    if ($post === null) {
                        Notification::make()->title('Post not found')->danger()->send();

                        return;
                    }

                    try {
                        app(CampaignService::class)->create($post->profile, $post, [
                            'duration_days' => (int) $data['duration_days'],
                        ]);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title(collect($e->errors())->flatten()->first() ?? 'Could not launch')
                            ->danger()
                            ->send();

                        return;
                    } catch (HttpException $e) {
                        Notification::make()
                            ->title($e->getMessage() ?: 'This post already has an active campaign.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Campaign launched')->success()->send();
                }),
        ];
    }
}
