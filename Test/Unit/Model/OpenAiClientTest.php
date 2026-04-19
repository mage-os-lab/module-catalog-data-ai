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
}
