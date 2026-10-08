<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Coloured initials for the README screenshots (ui-avatars.com, fetched by the browser).
 */
class ColouredAvatars implements AvatarProvider
{
    private const COLOURS = ['f43f5e', '0ea5e9', '8b5cf6', '10b981', '6366f1', 'f59e0b', '14b8a6', 'ec4899'];

    public function get(Model $record): string
    {
        return 'https://ui-avatars.com/api/?'.http_build_query([
            'name' => $record->getAttribute('name'),
            'background' => self::COLOURS[(int) $record->getKey() % count(self::COLOURS)],
            'color' => 'ffffff',
            'bold' => 'true',
        ]);
    }
}
