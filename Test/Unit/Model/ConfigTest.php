<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model;

use MageOS\CatalogDataAI\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
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

    /**
     * @return array<string, array<string, bool>>
     */
    public static function booleanFlagDataProvider(): array
    {
        return [
            'enabled' => ['enabled' => true],
            'disabled' => ['enabled' => false],
        ];
    }

    /**
     * @param bool $enabled
     * @dataProvider booleanFlagDataProvider
     */
    public function testIsEnabled(bool $enabled): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENRICH_ENABLED)
            ->willReturn($enabled);

        $this->assertSame($enabled, $this->config->isEnabled());
    }

    /**
     * @param bool $enabled
     * @dataProvider booleanFlagDataProvider
     */
    public function testIsAsync(bool $enabled): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_USE_ASYNC)
            ->willReturn($enabled);

        $this->assertSame($enabled, $this->config->isAsync());
    }

    /**
     * @param bool $enabled
     * @dataProvider booleanFlagDataProvider
     */
    public function testIsCacheEnabled(bool $enabled): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENRICHMENT_CACHE)
            ->willReturn($enabled);

        $this->assertSame($enabled, $this->config->isCacheEnabled());
    }

    /**
     * @param bool $enabled
     * @dataProvider booleanFlagDataProvider
     */
    public function testIsApprovalRequired(bool $enabled): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_APPROVAL_WORKFLOW)
            ->willReturn($enabled);

        $this->assertSame($enabled, $this->config->isApprovalRequired());
    }

    public function testGetApiKey(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_KEY)
            ->willReturn('sk-test-key-123');

        $this->assertSame('sk-test-key-123', $this->config->getApiKey());
    }

    public function testGetApiKeyReturnsEmptyStringWhenNull(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_KEY)
            ->willReturn(null);

        $this->assertSame('', $this->config->getApiKey());
    }

    public function testGetOrganizationId(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_ORGANIZATION_ID)
            ->willReturn('org-123');

        $this->assertSame('org-123', $this->config->getOrganizationId());
    }

    public function testGetOrganizationIdReturnsNull(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_ORGANIZATION_ID)
            ->willReturn(null);

        $this->assertNull($this->config->getOrganizationId());
    }

    public function testGetProjectId(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_PROJECT_ID)
            ->willReturn('proj-456');

        $this->assertSame('proj-456', $this->config->getProjectId());
    }

    public function testGetProjectIdReturnsNull(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_PROJECT_ID)
            ->willReturn(null);

        $this->assertNull($this->config->getProjectId());
    }

    public function testGetApiModel(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_MODEL)
            ->willReturn('gpt-4');

        $this->assertSame('gpt-4', $this->config->getApiModel());
    }

    public function testGetApiModelReturnsEmptyStringWhenNull(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_MODEL)
            ->willReturn(null);

        $this->assertSame('', $this->config->getApiModel());
    }

    public function testGetSystemPrompt(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT)
            ->willReturn('You are a helpful assistant');

        $this->assertSame('You are a helpful assistant', $this->config->getSystemPrompt());
    }

    public function testGetApiMaxTokens(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_MAX_TOKENS)
            ->willReturn('2048');

        $this->assertSame(2048, $this->config->getApiMaxTokens());
    }

    public function testGetApiMaxTokensCastsToInt(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_MAX_TOKENS)
            ->willReturn('1024');

        $this->assertSame(1024, $this->config->getApiMaxTokens());
    }

    public function testGetTemperature(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE)
            ->willReturn('0.7');

        $this->assertSame(0.7, $this->config->getTemperature());
    }

    public function testGetTemperatureCastsToFloat(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE)
            ->willReturn('1.5');

        $this->assertSame(1.5, $this->config->getTemperature());
    }

    public function testGetFrequencyPenalty(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY)
            ->willReturn('0.5');

        $this->assertSame(0.5, $this->config->getFrequencyPenalty());
    }

    public function testGetFrequencyPenaltyCastsToFloat(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY)
            ->willReturn('1.0');

        $this->assertSame(1.0, $this->config->getFrequencyPenalty());
    }

    public function testGetPresencePenalty(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY)
            ->willReturn('0.3');

        $this->assertSame(0.3, $this->config->getPresencePenalty());
    }

    public function testGetPresencePenaltyCastsToFloat(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY)
            ->willReturn('0.8');

        $this->assertSame(0.8, $this->config->getPresencePenalty());
    }

    public function testCanEnrichReturnsTrueWhenAllConditionsMet(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('isObjectNew')->willReturn(true);

        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_KEY)
            ->willReturn('sk-test-key');

        $this->assertTrue($this->config->canEnrich($product));
    }

    public function testCanEnrichReturnsFalseWhenDisabled(): void
    {
        $product = $this->createMock(Product::class);
        $product->expects($this->never())
            ->method('isObjectNew');

        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENRICH_ENABLED)
            ->willReturn(false);

        $this->assertFalse($this->config->canEnrich($product));
    }

    public function testCanEnrichReturnsFalseWhenNoApiKey(): void
    {
        $product = $this->createMock(Product::class);
        $product->expects($this->never())
            ->method('isObjectNew');

        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENRICH_ENABLED)
            ->willReturn(true);

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_KEY)
            ->willReturn('');

        $this->assertFalse($this->config->canEnrich($product));
    }

    public function testCanEnrichReturnsFalseWhenProductNotNew(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('isObjectNew')->willReturn(false);

        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_OPENAI_API_KEY)
            ->willReturn('sk-test-key');

        $this->assertFalse($this->config->canEnrich($product));
    }

    public function testGetProductPromptWithJsonString(): void
    {
        $jsonString = '[{"attribute_code":"description","prompt":"Generate description"}]';
        $arrayData = [
            ['attribute_code' => 'description', 'prompt' => 'Generate description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($jsonString);

        $this->json->expects($this->once())
            ->method('unserialize')
            ->with($jsonString)
            ->willReturn($arrayData);

        $this->assertSame('Generate description', $this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithArrayInput(): void
    {
        $arrayData = [
            ['attribute_code' => 'short_description', 'prompt' => 'Generate short description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->json->expects($this->never())
            ->method('unserialize');

        $this->assertSame('Generate short description', $this->config->getProductPrompt('short_description'));
    }

    public function testGetProductPromptWithInvalidJson(): void
    {
        $jsonString = 'invalid-json';

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($jsonString);

        $this->json->expects($this->once())
            ->method('unserialize')
            ->with($jsonString)
            ->willThrowException(new \InvalidArgumentException('Invalid JSON'));

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithNonArrayValue(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn('string-value');

        $this->json->expects($this->once())
            ->method('unserialize')
            ->with('string-value')
            ->willReturn('not-an-array');

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithEmptyRows(): void
    {
        $arrayData = [];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithMissingAttributeCode(): void
    {
        $arrayData = [
            ['prompt' => 'Generate description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithMissingPrompt(): void
    {
        $arrayData = [
            ['attribute_code' => 'description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithEmptyAttributeCode(): void
    {
        $arrayData = [
            ['attribute_code' => '', 'prompt' => 'Generate description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithEmptyPrompt(): void
    {
        $arrayData = [
            ['attribute_code' => 'description', 'prompt' => '']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertNull($this->config->getProductPrompt('description'));
    }

    public function testGetProductPromptWithStoreScope(): void
    {
        $storeId = 1;
        $arrayData = [
            ['attribute_code' => 'description', 'prompt' => 'Store-specific prompt']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS,
                ScopeInterface::SCOPE_STORES,
                $storeId
            )
            ->willReturn($arrayData);

        $this->assertSame('Store-specific prompt', $this->config->getProductPrompt('description', $storeId));
    }

    public function testGetProductPromptReturnsNullForNonExistentAttribute(): void
    {
        $arrayData = [
            ['attribute_code' => 'description', 'prompt' => 'Generate description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertNull($this->config->getProductPrompt('non_existent_attribute'));
    }

    public function testGetProductPromptWithNonArrayRow(): void
    {
        $arrayData = [
            'invalid-row',
            ['attribute_code' => 'description', 'prompt' => 'Generate description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertSame('Generate description', $this->config->getProductPrompt('description'));
    }

    public function testGetConfiguredAttributes(): void
    {
        $arrayData = [
            ['attribute_code' => 'description', 'prompt' => 'Generate description'],
            ['attribute_code' => 'short_description', 'prompt' => 'Generate short description']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn($arrayData);

        $this->assertSame(['description', 'short_description'], $this->config->getConfiguredAttributes());
    }

    public function testGetConfiguredAttributesReturnsEmptyWhenNoConfig(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS)
            ->willReturn([]);

        $this->assertSame([], $this->config->getConfiguredAttributes());
    }

    public function testGetConfiguredAttributesWithStoreScope(): void
    {
        $storeId = 2;
        $arrayData = [
            ['attribute_code' => 'meta_title', 'prompt' => 'Generate meta title']
        ];

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(
                Config::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS,
                ScopeInterface::SCOPE_STORES,
                $storeId
            )
            ->willReturn($arrayData);

        $this->assertSame(['meta_title'], $this->config->getConfiguredAttributes($storeId));
    }
}
