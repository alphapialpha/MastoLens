<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WebFingerService
{
    /**
     * Parse a Mastodon handle into username and instance domain.
     *
     * Accepts: user@instance.tld, @user@instance.tld
     */
    public function parseHandle(string $input): ?array
    {
        $input = trim($input);
        $input = ltrim($input, '@');

        if (!preg_match('/^([a-zA-Z0-9_]+)@([a-zA-Z0-9._-]+\.[a-zA-Z]{2,})$/', $input, $matches)) {
            return null;
        }

        return [
            'username' => strtolower($matches[1]),
            'instance_domain' => strtolower($matches[2]),
            'acct_normalized' => strtolower($matches[1]) . '@' . strtolower($matches[2]),
        ];
    }

    /**
     * Resolve a Mastodon handle via WebFinger to get the profile URL.
     */
    public function resolve(string $username, string $instanceDomain): ?array
    {
        $resource = "acct:{$username}@{$instanceDomain}";
        $url = "https://{$instanceDomain}/.well-known/webfinger";

        try {
            $response = Http::timeout(10)
                ->accept('application/jrd+json')
                ->get($url, ['resource' => $resource]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            $profileUrl = null;
            foreach ($data['links'] ?? [] as $link) {
                if (($link['rel'] ?? '') === 'self' && ($link['type'] ?? '') === 'application/activity+json') {
                    $profileUrl = $link['href'] ?? null;
                    break;
                }
            }

            return [
                'subject' => $data['subject'] ?? null,
                'profile_url' => $profileUrl,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
