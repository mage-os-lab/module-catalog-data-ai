<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model;

use MageOS\CatalogDataAI\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private ScopeConfigInterface $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function test_get_enrichable_attributes_returns_enabled_attributes(): void
    {
        $serializedData = [
            'row1' => ['attribute' => 'description', 'prompt' => 'describe {{name}}', 'enabled' => '1'],
            'row2' => ['attribute' => 'meta_title', 'prompt' => 'title for {{name}}', 'enabled' => '0'],
            'row3' => ['attribute' => 'short_description', 'prompt' => 'short desc for {{name}}', 'enabled' => '1'],
        ];

        $this->scopeConfig->method('getValue')
            ->with('catalog_ai/product/attribute_prompts')
            ->willReturn($serializedData);

        $result = $this->config->getEnrichableAttributes();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('short_description', $result);
        $this->assertArrayNotHasKey('meta_title', $result);
        $this->assertEquals('describe {{name}}', $result['description']);
        $this->assertEquals('short desc for {{name}}', $result['short_description']);
    }

    public function test_get_enrichable_attributes_returns_empty_when_null(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('catalog_ai/product/attribute_prompts')
            ->willReturn(null);

        $result = $this->config->getEnrichableAttributes();

        $this->assertEmpty($result);
    }

    public function test_get_product_prompt_returns_prompt_for_attribute(): void
    {
        $serializedData = [
            'row1' => ['attribute' => 'description', 'prompt' => 'describe {{name}}', 'enabled' => '1'],
            'row2' => ['attribute' => 'meta_title', 'prompt' => 'title for {{name}}', 'enabled' => '1'],
        ];

        $this->scopeConfig->method('getValue')
            ->with('catalog_ai/product/attribute_prompts')
            ->willReturn($serializedData);

        $this->assertEquals('describe {{name}}', $this->config->getProductPrompt('description'));
        $this->assertNull($this->config->getProductPrompt('nonexistent'));
    }
}
