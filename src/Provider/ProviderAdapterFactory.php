<?php

declare(strict_types=1);

namespace AIGateway\Provider;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory for runtime HTTP provider adapters.
 */
final class ProviderAdapterFactory
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

/** Build a provider adapter from catalog config. */
    public function createAdapter(string $name, array $config): HttpProviderAdapter
    {
        $format = $config['format'] ?? 'openai';
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['base_url'] ?? null;
        $completionsPath = $config['completions_path'] ?? null;

        return new HttpProviderAdapter(
            name: $name,
            httpClient: $this->httpClient,
            baseUrl: $baseUrl ?? match ($format) {
                'anthropic' => 'https://api.anthropic.com',
                default => 'https://api.openai.com/v1',
            },
            apiKey: $apiKey,
            completionsPath: $completionsPath,
            providerFormat: $format,
        );
    }
}
