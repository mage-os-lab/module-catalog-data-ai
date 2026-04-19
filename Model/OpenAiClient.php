<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use MageOS\CatalogDataAI\Api\AiClientInterface;
use OpenAI\Client;
use OpenAI\Factory;
use OpenAI\Responses\Meta\MetaInformation;

class OpenAiClient implements AiClientInterface
{
    private Client $client;

    /**
     * @param Factory $clientFactory
     * @param Config $config
     */
    public function __construct(
        private readonly Factory $clientFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function generate(string $systemPrompt, string $userPrompt): ?string
    {
        $response = $this->getClient()->chat()->create([
            'model' => $this->config->getApiModel(),
            'temperature' => $this->config->getTemperature(),
            'frequency_penalty' => $this->config->getFrequencyPenalty(),
            'presence_penalty' => $this->config->getPresencePenalty(),
            'max_completion_tokens' => $this->config->getApiMaxTokens(),
            'messages' => [
                [
                    'role' => 'developer',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ]);

        $this->backoff($response->meta());

        $result = $response->choices[0] ?? null;
        return $result?->message?->content;
    }

    private function getClient(): Client
    {
        if (!isset($this->client)) {
            $this->client = $this->clientFactory
                ->withOrganization($this->config->getOrganizationId())
                ->withApiKey($this->config->getApiKey())
                ->withProject($this->config->getProjectId())
                ->make();
        }

        return $this->client;
    }

    public function backoff(MetaInformation $meta): void
    {
        if ($meta->requestLimit->remaining < 1) {
            sleep($this->strToSeconds($meta->requestLimit->reset));
        }
        // 1 token ~= 0.75 word
        // do not use config value
        if ($meta->tokenLimit->remaining < 1000) {
            sleep($this->strToSeconds($meta->tokenLimit->reset));
        }
    }

    public function strToSeconds(string $time): float|int
    {
        preg_match('/(?:([0-9]+)h)?(?:([0-9]+)m)?(?:([0-9]+)s)?/', $time, $matches);

        $hours = isset($matches[1]) ? intval($matches[1]) : 0;
        $minutes = isset($matches[2]) ? intval($matches[2]) : 0;
        $seconds = isset($matches[3]) ? intval($matches[3]) : 0;

        return $hours * 3600 + $minutes * 60 + $seconds;
    }
}
