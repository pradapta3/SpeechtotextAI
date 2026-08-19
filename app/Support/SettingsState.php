<?php

declare(strict_types=1);

namespace App\Support;

class SettingsState
{
    public function __construct(
        private readonly ApiCredentials $credentials,
        private readonly UserPreferences $preferences,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'allow_user_keys' => (bool) config('notulensi.allow_user_keys'),
            'preferences' => $this->preferences->all(),
            'languages' => config('notulensi.languages'),
            'providers' => [
                ApiCredentials::GROQ => $this->providerState(ApiCredentials::GROQ),
                ApiCredentials::ANTHROPIC => $this->providerState(ApiCredentials::ANTHROPIC),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function providerState(string $provider): array
    {
        return [
            'server_key' => $this->credentials->hasServerKey($provider),
            'user_key' => $this->credentials->hasUserKey($provider),
            'masked' => $this->credentials->maskUserKey($provider),
        ];
    }
}
