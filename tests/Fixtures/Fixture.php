<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Tests\Fixtures;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Loads the recorded registry responses (see tests/Fixtures/*).
 */
final class Fixture
{
    public static function text(string $path): string
    {
        return (string) file_get_contents(__DIR__.'/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(string $path): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode(self::text($path), true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * Http::fake() answer for a recorded JSON fixture.
     */
    public static function jsonResponse(string $path, int $status = 200): mixed
    {
        return Http::response(self::text($path), $status, ['Content-Type' => 'application/json']);
    }

    public static function timeout(): \Closure
    {
        return static function (Request $request): never {
            throw new ConnectionException('cURL error 28: Operation timed out');
        };
    }
}
