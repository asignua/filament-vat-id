<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Registries;

use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use DOMDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * GUS BIR 1.1, the Polish REGON registry (SOAP). Needs an API key (register at https://api.stat.gov.pl); the
 * registry is disabled while `filament-vat-id.gus.key` is empty. `gus.environment = test` uses the public test
 * endpoint and key (the data there is scrambled).
 *
 * Speaks raw SOAP 1.2 over the HTTP client, so ext-soap is not required. The service answers with MTOM
 * (multipart/related); the envelope is cut out of it. The session id (`sid`) is cached and renewed on demand.
 * Returns the name, address, NIP, REGON and whether the business has been closed; it has no bank accounts.
 */
class GusBir extends AbstractRegistry
{
    private const string NS = 'http://CIS/BIR/PUBL/2014/07';

    public function name(): string
    {
        return 'gus_bir';
    }

    public function isEnabled(): bool
    {
        return $this->key() !== '';
    }

    public function supports(string $country, TaxIdType $type): bool
    {
        return $this->isEnabled()
            && $country === 'PL'
            && in_array($type, [TaxIdType::EuVat, TaxIdType::PlNip, TaxIdType::PlRegon], true);
    }

    public function lookup(string $country, string $number): ?CompanyData
    {
        if (!$this->isEnabled()) {
            throw new RegistryUnavailable($this->name(), 'No API key configured.');
        }

        $number = (string) preg_replace('/\D/', '', (string) preg_replace('/^PL/i', '', $number));

        $param = match (strlen($number)) {
            10 => 'Nip',
            9, 14 => 'Regon',
            default => null,
        };

        if ($param === null) {
            return null;
        }

        $records = $this->search($param, $number);

        if ($records === null) {
            return null;
        }

        return $this->map($records);
    }

    /**
     * @return array<string, string>|null `null` = no such entity
     */
    private function search(string $param, string $number, bool $retry = true): ?array
    {
        $sid = $this->session();

        $body = '<ns:DaneSzukajPodmioty><ns:pParametryWyszukiwania>'
            ."<dat:{$param}>{$number}</dat:{$param}>"
            .'</ns:pParametryWyszukiwania></ns:DaneSzukajPodmioty>';

        $result = $this->call('DaneSzukajPodmioty', $body, $sid, 'DaneSzukajPodmiotyResult');

        // An empty result means the session is gone (they last about an hour): log in again once.
        if ($result === '') {
            if (!$retry) {
                throw new RegistryUnavailable($this->name(), 'Empty answer (session rejected).');
            }

            $this->forgetSession();

            return $this->search($param, $number, false);
        }

        $xml = @simplexml_load_string($result);

        if (!$xml instanceof SimpleXMLElement || !isset($xml->dane)) {
            throw new RegistryUnavailable($this->name(), 'Unreadable result.');
        }

        $record = [];

        foreach ($xml->dane[0]?->children() ?? [] as $child) {
            $record[$child->getName()] = trim((string) $child);
        }

        if (isset($record['ErrorCode'])) {
            // 4 = nothing found; anything else (5 = not logged in, 7 = key problems…) is not a verdict on the number.
            if ($record['ErrorCode'] === '4') {
                return null;
            }

            if ($record['ErrorCode'] === '5' && $retry) {
                $this->forgetSession();

                return $this->search($param, $number, false);
            }

            throw new RegistryUnavailable($this->name(), 'ErrorCode '.$record['ErrorCode']);
        }

        return $record;
    }

    /**
     * @param array<string, string> $r
     */
    private function map(array $r): CompanyData
    {
        $street = trim(($r['Ulica'] ?? '') !== '' ? ($r['Ulica'].' '.($r['NrNieruchomosci'] ?? '')) : ($r['Miejscowosc'] ?? '').' '.($r['NrNieruchomosci'] ?? ''));
        $street = trim($street.(($r['NrLokalu'] ?? '') !== '' ? '/'.$r['NrLokalu'] : ''));

        $postcode = ($r['KodPocztowy'] ?? '') !== '' ? $r['KodPocztowy'] : null;
        $city = ($r['Miejscowosc'] ?? '') !== '' ? $r['Miejscowosc'] : null;

        $address = trim($street.', '.trim(($postcode ?? '').' '.($city ?? '')), ' ,');

        $closed = ($r['DataZakonczeniaDzialalnosci'] ?? '') !== '';
        $nip = ($r['Nip'] ?? '') !== '' ? $r['Nip'] : null;

        return new CompanyData(
            name: $r['Nazwa'] ?? '',
            source: $this->name(),
            address: $address === '' ? null : $address,
            street: $street === '' ? null : $street,
            city: $city,
            postcode: $postcode,
            country: 'PL',
            vatNumber: $nip === null ? null : 'PL'.$nip,
            regon: ($r['Regon'] ?? '') !== '' ? $r['Regon'] : null,
            active: !$closed,
            status: $closed ? 'closed' : 'active',
            raw: $r,
        );
    }

    private function session(): string
    {
        $cache = Cache::store($this->cacheStore());
        $key = 'filament-vat-id:gus:sid:'.$this->environment().':'.sha1($this->key());

        $sid = $cache->get($key);

        if (is_string($sid) && $sid !== '') {
            return $sid;
        }

        $sid = $this->call(
            'Zaloguj',
            '<ns:Zaloguj><ns:pKluczUzytkownika>'.htmlspecialchars($this->key(), ENT_XML1).'</ns:pKluczUzytkownika></ns:Zaloguj>',
            null,
            'ZalogujResult',
        );

        // A wrong key is answered with an empty session id.
        if ($sid === '') {
            throw new RegistryUnavailable($this->name(), 'Login rejected (check the API key).');
        }

        $cache->put($key, $sid, now()->addMinutes(50));

        return $sid;
    }

    private function forgetSession(): void
    {
        Cache::store($this->cacheStore())->forget('filament-vat-id:gus:sid:'.$this->environment().':'.sha1($this->key()));
    }

    private function call(string $action, string $body, ?string $sid, string $resultTag): string
    {
        $endpoint = $this->endpoint();

        $envelope = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="'.self::NS.'" xmlns:dat="'.self::NS.'/DataContract">'
            .'<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">'
            ."<wsa:To>{$endpoint}</wsa:To>"
            .'<wsa:Action>'.self::NS."/IUslugaBIRzewnPubl/{$action}</wsa:Action>"
            .'</soap:Header><soap:Body>'.$body.'</soap:Body></soap:Envelope>';

        $headers = $sid === null ? [] : ['sid' => $sid];

        $response = $this->send(fn () => $this->http()
            ->withHeaders($headers)
            ->withBody($envelope, 'application/soap+xml; charset=utf-8')
            ->post($endpoint));

        if (!$response->successful()) {
            throw new RegistryUnavailable($this->name(), 'HTTP '.$response->status());
        }

        return self::extractResult($response->body(), $resultTag, $this->name());
    }

    /**
     * Pulls the text of `<$resultTag>` out of a SOAP response that may be wrapped in MTOM/multipart parts.
     */
    public static function extractResult(string $body, string $resultTag, string $registry = 'gus_bir'): string
    {
        // Cut the envelope out of the multipart wrapper (or take the body as it is).
        if (preg_match('~<(?:[\w.-]+:)?Envelope\b.*</(?:[\w.-]+:)?Envelope>~s', $body, $m) !== 1) {
            throw new RegistryUnavailable($registry, 'No SOAP envelope in the response.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument;

            if (!$dom->loadXML($m[0])) {
                throw new RegistryUnavailable($registry, 'Malformed SOAP envelope.');
            }

            if ($dom->getElementsByTagNameNS('*', 'Fault')->length > 0) {
                throw new RegistryUnavailable($registry, 'SOAP fault.');
            }

            $node = $dom->getElementsByTagNameNS('*', $resultTag)->item(0);

            return $node === null ? '' : trim($node->textContent);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function key(): string
    {
        $key = trim((string) config('filament-vat-id.gus.key', ''));

        if ($key === '' && $this->environment() === 'test') {
            return $this->configString('gus.test_key');
        }

        return $key;
    }

    private function environment(): string
    {
        return config('filament-vat-id.gus.environment') === 'test' ? 'test' : 'prod';
    }

    private function endpoint(): string
    {
        return $this->configString('gus.endpoints.'.$this->environment());
    }

    private function cacheStore(): ?string
    {
        $store = config('filament-vat-id.cache.store');

        return is_string($store) && $store !== '' ? $store : null;
    }
}
