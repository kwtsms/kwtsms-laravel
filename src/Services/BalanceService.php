<?php

namespace KwtSMS\Laravel\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use KwtSMS\Laravel\Models\KwtSmsSetting;

/**
 * Balance caching and sync service.
 *
 * Caches the kwtSMS account balance in the kwtsms_settings table.
 * After every send(), SmsSender calls updateCache() with the balance-after
 * value from the API response, so we avoid redundant API calls.
 *
 * Settings keys:
 *   - 'balance_available': float, last known available balance
 *   - 'balance_synced_at': ISO datetime string of last sync
 */
class BalanceService
{
    private const BALANCE_KEY = 'balance_available';

    private const SYNCED_AT_KEY = 'balance_synced_at';

    /**
     * Get the cached balance from the database.
     *
     * Returns null if no balance has been synced yet.
     */
    public function getCached(): ?float
    {
        $value = KwtSmsSetting::get(self::BALANCE_KEY);

        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Update the cached balance (called after every successful send).
     */
    public function updateCache(float $balance): void
    {
        KwtSmsSetting::set(self::BALANCE_KEY, $balance);
        KwtSmsSetting::set(self::SYNCED_AT_KEY, now()->toIso8601String());
    }

    /**
     * Get the timestamp of the last balance sync.
     */
    public function getSyncedAt(): ?\DateTimeInterface
    {
        $value = KwtSmsSetting::get(self::SYNCED_AT_KEY);

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Get cached balance, syncing from API first if stale or unknown.
     *
     * "Stale" means: never synced, or last sync was more than 24 hours ago.
     * If sync fails (network error, API down), logs a warning and returns
     * the cached value anyway (may be null).
     */
    public function getCachedOrSync(): ?float
    {
        $syncedAt = $this->getSyncedAt();
        $isStale = $syncedAt === null || $syncedAt->diffInHours(now()) >= 24;

        if ($isStale) {
            $result = $this->syncFromApi();

            if (isset($result['error'])) {
                Log::warning('KwtSMS: balance sync failed, using cached value', [
                    'error' => $result['error'],
                ]);
            }
        }

        return $this->getCached();
    }

    /**
     * Sync balance from the kwtSMS API and update the cache.
     *
     * Calls /API/balance/ directly. The kwtsms/kwtsms-php library balance() method
     * reads the wrong field ('balance' instead of 'available'), so we call
     * the API ourselves to get the correct value.
     *
     * @return array{available?: float, purchased?: float, error?: string}
     */
    public function syncFromApi(): array
    {
        $response = $this->fetchBalanceFromApi();

        if (! isset($response['result']) || $response['result'] !== 'OK') {
            return ['error' => $response['description'] ?? 'Failed to retrieve balance from API'];
        }

        $available = (float) ($response['available'] ?? 0);
        $purchased = (float) ($response['purchased'] ?? 0);

        $this->updateCache($available);

        return ['available' => $available, 'purchased' => $purchased];
    }

    /**
     * Fetch balance from the kwtSMS /API/balance/ endpoint.
     *
     * @return array<string, mixed>
     */
    private function fetchBalanceFromApi(): array
    {
        $url = rtrim((string) config('kwtsms.api_base_url', 'https://www.kwtsms.com/API/'), '/').'/balance/';

        try {
            $response = Http::timeout((int) config('kwtsms.timeout', 30))
                ->connectTimeout(5)
                ->acceptJson()
                ->withUserAgent(UserAgent::token()) // 2026-11-05 14:55 +03: identify the integration, #939
                ->post($url, [
                    'username' => config('kwtsms.username', ''),
                    'password' => config('kwtsms.password', ''),
                ]);

            $decoded = $response->json();

            return is_array($decoded) ? $decoded : ['result' => 'ERROR', 'description' => 'Invalid JSON from balance API'];
        } catch (\Throwable $e) {
            return ['result' => 'ERROR', 'description' => $e->getMessage()];
        }
    }
}
