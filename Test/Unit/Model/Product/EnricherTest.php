<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\EnrichmentRecorder;
use MageOS\CatalogDataAI\Model\Product\HashGenerator;
use OpenAI\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnricherTest extends TestCase
{
    private Config&MockObject $config;
    private HashGenerator&MockObject $hashGenerator;
    private EnrichmentRecorder&MockObject $enrichmentRecorder;
    private Enricher $enricher;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->hashGenerator = $this->createMock(HashGenerator::class);
        $this->enrichmentRecorder = $this->createMock(EnrichmentRecorder::class);

        // OpenAI\Factory is final — use a real instance (it has no side effects in constructor)
        $this->enricher = new Enricher(
            new Factory(),
            $this->config,
            $this->hashGenerator,
            $this->enrichmentRecorder
        );
    }

    // --- parsePrompt tests ---

    public function testParsePromptReplacesPlaceholders(): void
    {
        $product = $this->createProductMock(['name' => 'Widget']);
        $result = $this->enricher->parsePrompt('Describe {{name}}', $product);

        $this->assertSame('Describe Widget', $result);
    }

    public function testParsePromptNullAttributeReturnsEmptyString(): void
    {
        $product = $this->createProductMock([]);
        $result = $this->enricher->parsePrompt('{{missing}}', $product);

        $this->assertSame('', $result);
    }

    public function testParsePromptNoPlaceholdersUnchanged(): void
    {
        $product = $this->createProductMock([]);
        $result = $this->enricher->parsePrompt('Static prompt', $product);

        $this->assertSame('Static prompt', $result);
    }

    public function testParsePromptMultipleSamePlaceholder(): void
    {
        $product = $this->createProductMock(['name' => 'Widget']);
        $result = $this->enricher->parsePrompt('{{name}} is {{name}}', $product);

        $this->assertSame('Widget is Widget', $result);
    }

    // --- enrichAttribute guard clause tests ---

    public function testEnrichAttributeSkipsExistingValueWithoutOverwrite(): void
    {
        $product = $this->createProductMock([
            'description' => 'Existing description',
            'mageos_catalogai_overwrite' => false,
        ]);

        $this->config->expects($this->never())->method('getProductPrompt');
        $this->hashGenerator->expects($this->never())->method('generate');

        $this->enricher->enrichAttribute($product, 'description');
    }

    public function testEnrichAttributeSkipsWhenNoPromptConfigured(): void
    {
        $product = $this->createProductMock(['store_id' => 1]);

        $this->config->expects($this->once())
            ->method('getProductPrompt')
            ->with('description', 1)
            ->willReturn(null);

        $this->hashGenerator->expects($this->never())->method('generate');

        $this->enricher->enrichAttribute($product, 'description');
    }

    // --- enrichAttribute cache hit tests ---

    public function testEnrichAttributeCacheHitApprovedSetsValue(): void
    {
        $setDataCalls = [];
        $product = $this->createProductMockForCacheTest(
            ['name' => 'Widget'],
            $setDataCalls
        );

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPROVED);
        $enrichment->method('getGeneratedValue')->willReturn('AI text');
        $enrichment->method('getAppliedValue')->willReturn(null);

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $this->assertContains(['key' => 'description', 'value' => 'AI text'], $setDataCalls);
    }

    public function testEnrichAttributeCacheHitAppliedUsesAppliedValue(): void
    {
        $setDataCalls = [];
        $product = $this->createProductMockForCacheTest(
            ['name' => 'Widget'],
            $setDataCalls
        );

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);
        $enrichment->method('getGeneratedValue')->willReturn('original');
        $enrichment->method('getAppliedValue')->willReturn('edited');

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $this->assertContains(['key' => 'description', 'value' => 'edited'], $setDataCalls);
    }

    public function testEnrichAttributeCacheHitPendingSkips(): void
    {
        $setDataCalls = [];
        $product = $this->createProductMockForCacheTest(
            ['name' => 'Widget'],
            $setDataCalls
        );

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($setDataCalls, fn($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    public function testEnrichAttributeCacheHitDeniedSkips(): void
    {
        $setDataCalls = [];
        $product = $this->createProductMockForCacheTest(
            ['name' => 'Widget'],
            $setDataCalls
        );

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_DENIED);

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($setDataCalls, fn($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    // --- enrichAttribute with overwrite ---

    public function testEnrichAttributeProcessesWhenOverwriteSet(): void
    {
        $calls = [];
        $product = $this->createProductMockForCacheTest(
            ['name' => 'Widget', 'description' => 'Existing', 'mageos_catalogai_overwrite' => true],
            $calls
        );

        $this->config->method('getProductPrompt')
            ->with('description', 1)
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->hashGenerator->expects($this->once())
            ->method('generate')
            ->with('Describe Widget', 'system', 'description', 1)
            ->willReturn('somehash');

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPROVED);
        $enrichment->method('getGeneratedValue')->willReturn('AI text');
        $enrichment->method('getAppliedValue')->willReturn(null);

        $this->enrichmentRecorder->method('findByHash')->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');
    }

    // --- strToSeconds via reflection ---

    public function testStrToSecondsFullFormat(): void
    {
        $reflection = new \ReflectionMethod(Enricher::class, 'strToSeconds');
        $result = $reflection->invoke($this->enricher, '1h2m3s');

        $this->assertSame(3723, $result);
    }

    public function testStrToSecondsSecondsOnly(): void
    {
        $reflection = new \ReflectionMethod(Enricher::class, 'strToSeconds');
        $result = $reflection->invoke($this->enricher, '30s');

        $this->assertSame(30, $result);
    }

    public function testStrToSecondsEmptyString(): void
    {
        $reflection = new \ReflectionMethod(Enricher::class, 'strToSeconds');
        $result = $reflection->invoke($this->enricher, '');

        $this->assertSame(0, $result);
    }

    // --- getAttributes ---

    public function testGetAttributesDelegatesToConfig(): void
    {
        $this->config->expects($this->once())
            ->method('getConfiguredAttributes')
            ->with(null)
            ->willReturn(['name', 'description']);

        $result = $this->enricher->getAttributes();

        $this->assertSame(['name', 'description'], $result);
    }

    public function testGetAttributesPassesStoreId(): void
    {
        $this->config->expects($this->once())
            ->method('getConfiguredAttributes')
            ->with(5)
            ->willReturn(['short_description']);

        $result = $this->enricher->getAttributes(5);

        $this->assertSame(['short_description'], $result);
    }

    // --- Helpers ---

    private function createProductMock(array $data): Product&MockObject
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData', 'getStoreId', 'getId'])
            ->getMock();

        $product->method('getData')
            ->willReturnCallback(function (?string $key = null) use ($data) {
                if ($key === null) {
                    return $data;
                }
                return $data[$key] ?? null;
            });

        $product->method('getStoreId')->willReturn($data['store_id'] ?? 0);
        $product->method('getId')->willReturn($data['entity_id'] ?? null);

        return $product;
    }

    private function createProductMockForCacheTest(array $data, array &$setDataCalls): Product&MockObject
    {
        $setDataCalls = [];
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData', 'getStoreId', 'getId'])
            ->getMock();

        $product->method('getData')
            ->willReturnCallback(function (?string $key = null) use ($data) {
                if ($key === null) {
                    return $data;
                }
                return $data[$key] ?? null;
            });

        $product->method('setData')
            ->willReturnCallback(function (string $key, $value) use ($product, &$setDataCalls) {
                $setDataCalls[] = ['key' => $key, 'value' => $value];
                return $product;
            });

        $product->method('getStoreId')->willReturn(1);
        $product->method('getId')->willReturn($data['entity_id'] ?? 123);

        return $product;
    }

    private function setUpCacheHitScenario(
        string $expectedParsedPrompt,
        string $hash,
        int $storeId
    ): void {
        $this->config->method('getProductPrompt')
            ->with('description', $storeId)
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->hashGenerator->method('generate')
            ->with($expectedParsedPrompt, 'system', 'description', $storeId)
            ->willReturn($hash);
    }
}
