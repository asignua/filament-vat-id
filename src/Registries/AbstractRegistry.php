<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Registries;

use Asignua\FilamentVatId\Contracts\CompanyRegistry;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing for the HTTP registries: configured timeouts and the translation of transport failures into
 * {@see RegistryUnavailable}.
 */
abstract class AbstractRegistry implements CompanyRegistry
{
    /**
     * A short machine name, stored in `CompanyData::$source`.
     */
    abstract public function name(): string;

    protected function http(): PendingRequest
    {
        return Http::connectTimeout((int) config('filament-vat-id.timeouts.connect', 5))
            ->timeout((int) config('filament-vat-id.timeouts.request', 10))
            ->withHeaders(['User-Agent' => 'asignua-filament-vat-id']);
    }

    /**
     * Runs an HTTP call; a timeout or connection failure becomes RegistryUnavailable.
     *
     * @param callable(): Response $call
     */
    protected function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException|RequestException $e) {
            // A transport error after the headers arrived carries the 4xx/5xx response and surfaces as RequestException.
            throw new RegistryUnavailable($this->name(), $e->getMessage(), $e);
        }
    }

    protected function configString(string $key, string $default = ''): string
    {
        $value = config('filament-vat-id.'.$key, $default);

        return is_string($value) ? $value : $default;
    }

    protected static function clean(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '---' ? null : $value;
    }
}
