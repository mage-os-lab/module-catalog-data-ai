<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model;

use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\OpenAiClient;
use OpenAI\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OpenAiClientTest extends TestCase
{
    private Config&MockObject $config;
    private OpenAiClient $client;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);

        // OpenAI\Factory is final — use a real instance (it has no side effects in constructor)
        $this->client = new OpenAiClient(
            new Factory(),
            $this->config
        );
    }

    public function testStrToSecondsFullFormat(): void
    {
        $result = $this->client->strToSeconds('1h2m3s');

        $this->assertSame(3723, $result);
    }

    public function testStrToSecondsSecondsOnly(): void
    {
        $result = $this->client->strToSeconds('30s');

        $this->assertSame(30, $result);
    }

    public function testStrToSecondsEmptyString(): void
    {
        $result = $this->client->strToSeconds('');

        $this->assertSame(0, $result);
    }

    public function testBuildBatchSchemaCreatesRequiredStringProperties(): void
    {
        $prompts = [
            'description' => 'Write a description',
            'meta_title' => 'Write a meta title',
        ];

        $schema = $this->client->buildBatchSchema($prompts);

        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['description', 'meta_title'], $schema['required']);
        $this->assertSame('string', $schema['properties']['description']['type']);
        $this->assertSame('Write a description', $schema['properties']['description']['description']);
        $this->assertSame('string', $schema['properties']['meta_title']['type']);
    }

    public function testBuildBatchPromptCombinesContextAndInstructions(): void
    {
        $context = "Name: Widget\nSKU: ABC";
        $prompts = [
            'description' => 'Describe this product',
            'meta_title' => 'Write a title',
        ];

        $result = $this->client->buildBatchPrompt($context, $prompts);

        $this->assertStringContainsString('Product information:', $result);
        $this->assertStringContainsString("Name: Widget\nSKU: ABC", $result);
        $this->assertStringContainsString('description: Describe this product', $result);
        $this->assertStringContainsString('meta_title: Write a title', $result);
    }

    public function testParseBatchResponseValidJson(): void
    {
        $json = '{"description": "A great widget", "meta_title": "Widget Title"}';
        $requested = ['description' => 'prompt1', 'meta_title' => 'prompt2'];

        $result = $this->client->parseBatchResponse($json, $requested);

        $this->assertSame([
            'description' => 'A great widget',
            'meta_title' => 'Widget Title',
        ], $result);
    }

    public function testParseBatchResponseFiltersUnrequestedKeys(): void
    {
        $json = '{"description": "text", "meta_title": "title", "extra": "ignored"}';
        $requested = ['description' => 'prompt1', 'meta_title' => 'prompt2'];

        $result = $this->client->parseBatchResponse($json, $requested);

        $this->assertArrayNotHasKey('extra', $result);
        $this->assertCount(2, $result);
    }

    public function testParseBatchResponseInvalidJsonReturnsEmpty(): void
    {
        $result = $this->client->parseBatchResponse('not json', ['description' => 'p']);

        $this->assertSame([], $result);
    }

    public function testParseBatchResponseNullReturnsEmpty(): void
    {
        $result = $this->client->parseBatchResponse(null, ['description' => 'p']);

        $this->assertSame([], $result);
    }
}
