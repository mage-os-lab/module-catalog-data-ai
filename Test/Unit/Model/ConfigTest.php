<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model;

use InvalidArgumentException;
use MageOS\CatalogDataAI\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private Json&MockObject $json;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->json = $this->createMock(Json::class);
        $this->config = new Config($this->scopeConfig, $this->json);
    }

    public function test_get_configured_attributes_reads_array_format_from_defaults(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn([
                'short_description' => ['attribute_code' => 'short_description', 'prompt' => 'foo'],
                'description' => ['attribute_code' => 'description', 'prompt' => 'bar'],
            ]);

        $this->assertSame(
            ['short_description', 'description'],
            $this->config->getConfiguredAttributes()
        );
    }

    public function test_get_configured_attributes_reads_json_format_from_db(): void
    {
        $serialized = '{"r1":{"attribute_code":"color","prompt":"p"}}';
        $this->scopeConfig->method('getValue')->willReturn($serialized);
        $this->json
            ->expects($this->once())
            ->method('unserialize')
            ->with($serialized)
            ->willReturn(['r1' => ['attribute_code' => 'color', 'prompt' => 'p']]);

        $this->assertSame(['color'], $this->config->getConfiguredAttributes());
    }

    public function test_get_configured_attributes_skips_rows_with_empty_attribute_code(): void
    {
        $this->scopeConfig->method('getValue')->willReturn([
            'r1' => ['attribute_code' => '', 'prompt' => 'p'],
            'r2' => ['attribute_code' => 'color', 'prompt' => 'p2'],
        ]);

        $this->assertSame(['color'], $this->config->getConfiguredAttributes());
    }

    public function test_get_configured_attributes_skips_rows_with_empty_prompt(): void
    {
        $this->scopeConfig->method('getValue')->willReturn([
            'r1' => ['attribute_code' => 'color', 'prompt' => ''],
            'r2' => ['attribute_code' => 'size', 'prompt' => 'p'],
        ]);

        $this->assertSame(['size'], $this->config->getConfiguredAttributes());
    }

    public function test_get_configured_attributes_skips_non_array_rows(): void
    {
        $this->scopeConfig->method('getValue')->willReturn([
            'r1' => 'not-an-array',
            'r2' => ['attribute_code' => 'color', 'prompt' => 'p'],
        ]);

        $this->assertSame(['color'], $this->config->getConfiguredAttributes());
    }

    public function test_get_configured_attributes_returns_empty_for_null_value(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame([], $this->config->getConfiguredAttributes());
    }

    public function test_get_configured_attributes_returns_empty_for_malformed_json(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('{not-json');
        $this->json
            ->method('unserialize')
            ->willThrowException(new InvalidArgumentException('bad json'));

        $this->assertSame([], $this->config->getConfiguredAttributes());
    }

    public function test_get_product_prompt_returns_configured_value(): void
    {
        $this->scopeConfig->method('getValue')->willReturn([
            'r1' => ['attribute_code' => 'color', 'prompt' => 'extract color'],
        ]);

        $this->assertSame('extract color', $this->config->getProductPrompt('color'));
    }

    public function test_get_product_prompt_returns_null_for_unknown_attribute(): void
    {
        $this->scopeConfig->method('getValue')->willReturn([
            'r1' => ['attribute_code' => 'color', 'prompt' => 'p'],
        ]);

        $this->assertNull($this->config->getProductPrompt('unknown'));
    }

    public function test_get_product_prompt_scopes_to_store_when_given(): void
    {
        $this->scopeConfig
            ->expects($this->once())
            ->method('getValue')
            ->with(
                Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS,
                ScopeInterface::SCOPE_STORES,
                42
            )
            ->willReturn([
                'r1' => ['attribute_code' => 'color', 'prompt' => 'store-specific'],
            ]);

        $this->assertSame('store-specific', $this->config->getProductPrompt('color', 42));
    }

    public function test_results_are_cached_per_store_id(): void
    {
        $this->scopeConfig
            ->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnCallback(function (string $path, ?string $scope = null, $scopeId = null) {
                return match ($scopeId) {
                    42 => ['r1' => ['attribute_code' => 'color', 'prompt' => 'store42']],
                    default => ['r1' => ['attribute_code' => 'color', 'prompt' => 'default']],
                };
            });

        $this->assertSame('default', $this->config->getProductPrompt('color'));
        $this->assertSame('default', $this->config->getProductPrompt('color'));
        $this->assertSame('store42', $this->config->getProductPrompt('color', 42));
        $this->assertSame('store42', $this->config->getProductPrompt('color', 42));
    }
}
