<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MastodonApiService
{
    /**
     * Look up an account by username on a given instance.
     * Uses the public /api/v1/accounts/lookup endpoint.
     */
    public function lookupAccount(string $username, string $instanceDomain): ?array
    {
        $url = "https://{$instanceDomain}/api/v1/accounts/lookup";

        try {
            $response = Http::timeout(10)
                ->get($url, ['acct' => $username]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch the latest public statuses for an account.
     */
    public function getAccountStatuses(string $instanceDomain, string $remoteAccountId, int $limit = 20): ?array
    {
        $url = "https://{$instanceDomain}/api/v1/accounts/{$remoteAccountId}/statuses";

        try {
            $response = Http::timeout(15)
                ->get($url, [
                    'limit' => $limit,
                    'exclude_replies' => false,
                    'exclude_reblogs' => false,
                ]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch a single status by ID to get current metrics.
     *
     * Returns an array with:
     *   'status'    => 'ok' | 'not_found' | 'error'
     *   'data'      => array|null  (the status JSON when 'ok')
     *   'http_code' => int|null
     */
    public function getStatus(string $instanceDomain, string $remoteStatusId): array
    {
        $url = "https://{$instanceDomain}/api/v1/statuses/{$remoteStatusId}";

        try {
            $response = Http::timeout(10)
                ->get($url);

            if ($response->successful()) {
                return [
                    'status' => 'ok',
                    'data' => $response->json(),
                    'http_code' => $response->status(),
                ];
            }

            if (in_array($response->status(), [404, 410])) {
                return [
                    'status' => 'not_found',
                    'data' => null,
                    'http_code' => $response->status(),
                ];
            }

            return [
                'status' => 'error',
                'data' => null,
                'http_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'data' => null,
                'http_code' => null,
            ];
        }
    }
}
