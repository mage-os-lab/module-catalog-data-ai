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
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ]
        ]);

        $this->backoff($response->meta());

        $result = $response->choices[0] ?? null;
        return $result?->message?->content;
    }

    /**
     * @param array<string, string> $attributePrompts
     * @return array<string, mixed>
     */
    public function buildBatchSchema(array $attributePrompts): array
    {
        $properties = [];
        foreach ($attributePrompts as $code => $prompt) {
            $properties[$code] = ['type' => 'string', 'description' => $prompt];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($attributePrompts),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param string $productContext
     * @param array<string, string> $attributePrompts
     * @return string
     */
    public function buildBatchPrompt(string $productContext, array $attributePrompts): string
    {
        $prompt = "Product information:\n" . $productContext . "\n\n"
            . "Generate content for each of the following product attributes:\n";

        foreach ($attributePrompts as $code => $instruction) {
            $prompt .= "\n{$code}: {$instruction}";
        }

        return $prompt;
    }

    /**
     * @param string|null $json
     * @param array<string, string> $requestedKeys
     * @return array<string, string>
     */
    public function parseBatchResponse(?string $json, array $requestedKeys): array
    {
        $decoded = json_decode($json ?? '', true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_intersect_key($decoded, $requestedKeys);
    }

    /**
     * @inheritdoc
     */
    public function generateBatch(
        string $systemPrompt,
        string $productContext,
        array $attributePrompts
    ): array {
        $response = $this->getClient()->chat()->create([
            'model' => $this->config->getApiModel(),
            'temperature' => $this->config->getTemperature(),
            'frequency_penalty' => $this->config->getFrequencyPenalty(),
            'presence_penalty' => $this->config->getPresencePenalty(),
            'max_completion_tokens' => $this->config->getApiMaxTokens() * count($attributePrompts),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'product_enrichment',
                    'strict' => true,
                    'schema' => $this->buildBatchSchema($attributePrompts),
                ],
            ],
            'messages' => [
                ['role' => 'developer', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $this->buildBatchPrompt($productContext, $attributePrompts)],
            ],
        ]);

        $this->backoff($response->meta());

        $content = $response->choices[0]?->message?->content;

        return $this->parseBatchResponse($content, $attributePrompts);
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
        if($meta->requestLimit->remaining < 1) {
            sleep($this->strToSeconds($meta->requestLimit->reset));
        }
        // 1 token ~= 0.75 word
        // do not use config value
        if($meta->tokenLimit->remaining < 1000) {
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
