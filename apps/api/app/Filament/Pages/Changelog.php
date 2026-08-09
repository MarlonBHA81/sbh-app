<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Super-admin release notes. Reads the release history from config/version.php
 * (the same source as the footer version stamp) so confirming what's deployed
 * is a glance at this page + the footer. Gated to super admins in both the
 * navigation and on direct URL.
 */
class Changelog extends Page
{
    protected string $view = 'filament.pages.changelog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Changelog';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    /** Current deployed version string, e.g. "1.1.0". */
    public function version(): string
    {
        return (string) config('version.number');
    }

    public function releasedOn(): string
    {
        return (string) config('version.released');
    }

    /**
     * The release history, newest first.
     *
     * @return array<int, array{version:string, date:string, title:string, changes:array<int,string>}>
     */
    public function releases(): array
    {
        return (array) config('version.releases', []);
    }
}
