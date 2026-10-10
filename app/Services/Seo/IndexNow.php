<?php

namespace App\Services\Seo;

use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * IndexNow (free, open protocol): tells Bing, Yandex, Seznam, Naver and other
 * participating search engines about new or changed URLs immediately.
 */
class IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function key(): ?string
    {
        return $this->settings->get('indexnow_key') ?: null;
    }

    public function generateKey(): string
    {
        $key = bin2hex(random_bytes(16));
        $this->settings->set(['indexnow_key' => $key]);

        return $key;
    }

    public function keyUrl(): ?string
    {
        return $this->key() ? url($this->key().'.txt') : null;
    }

    public function host(): string
    {
        return (string) parse_url(url('/'), PHP_URL_HOST);
    }

    public function isPublicHost(): bool
    {
        $host = $this->host();

        return $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with($host, '.test') && ! str_ends_with($host, '.localhost');
    }

    /**
     * @param  list<string>  $urls
     * @return array{ok: bool, message: string}
     */
    public function submit(array $urls): array
    {
        if (! $this->key()) {
            return ['ok' => false, 'message' => 'Generate an IndexNow key first.'];
        }

        if (! $this->isPublicHost()) {
            return ['ok' => false, 'message' => 'IndexNow only works on a public domain. Set APP_URL to your real https:// address.'];
        }

        try {
            $response = Http::timeout(20)->acceptJson()->post(self::ENDPOINT, [
                'host' => $this->host(),
                'key' => $this->key(),
                'keyLocation' => $this->keyUrl(),
                'urlList' => array_values(array_slice($urls, 0, 10000)),
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'IndexNow could not be reached. Please try again later.'];
        }

        return match (true) {
            $response->successful() => ['ok' => true, 'message' => count($urls).' URLs were submitted to IndexNow.'],
            $response->status() === 403 => ['ok' => false, 'message' => 'IndexNow could not verify the key file. Make sure '.$this->keyUrl().' is reachable.'],
            $response->status() === 422 => ['ok' => false, 'message' => 'IndexNow rejected the URLs: they must belong to '.$this->host().'.'],
            $response->status() === 429 => ['ok' => false, 'message' => 'Too many submissions. Please try again later.'],
            default => ['ok' => false, 'message' => 'IndexNow answered with HTTP '.$response->status().'.'],
        };
    }
}
