<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Api\AiClientInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\EnrichmentRecorder;
use MageOS\CatalogDataAI\Model\Product\HashGenerator;
use MageOS\CatalogDataAI\Model\Product\PromptResolver;
use MageOS\CatalogDataAI\Test\Unit\Trait\ProductMockTrait;
use OpenAI\Exceptions\ErrorException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnricherTest extends TestCase
{
    use ProductMockTrait;

    private AiClientInterface&MockObject $aiClient;
    private Config&MockObject $config;
    private HashGenerator&MockObject $hashGenerator;
    private EnrichmentRecorder&MockObject $enrichmentRecorder;
    private PromptResolver&MockObject $promptResolver;
    private Enricher $enricher;

    protected function setUp(): void
    {
        $this->aiClient = $this->createMock(AiClientInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->hashGenerator = $this->createMock(HashGenerator::class);
        $this->enrichmentRecorder = $this->createMock(EnrichmentRecorder::class);
        $this->promptResolver = $this->createMock(PromptResolver::class);

        $this->enricher = new Enricher(
            $this->aiClient,
            $this->config,
            $this->hashGenerator,
            $this->enrichmentRecorder,
            $this->promptResolver
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

        $this->promptResolver->expects($this->never())->method('resolve');
        $this->hashGenerator->expects($this->never())->method('generate');

        $this->enricher->enrichAttribute($product, 'description');
    }

    public function testEnrichAttributeSkipsWhenNoPromptConfigured(): void
    {
        $product = $this->createProductMock(['store_id' => 1], storeId: 1);

        $this->promptResolver->expects($this->once())
            ->method('resolve')
            ->willReturn(null);

        $this->hashGenerator->expects($this->never())->method('generate');

        $this->enricher->enrichAttribute($product, 'description');
    }

    // --- enrichAttribute cache hit tests ---

    public function testEnrichAttributeCacheHitApprovedSetsValue(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 123, trackSetData: true);

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPROVED);
        $enrichment->method('getGeneratedValue')->willReturn('AI text');
        $enrichment->method('getAppliedValue')->willReturn(null);

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $this->assertContains(['key' => 'description', 'value' => 'AI text'], $this->getProductSetDataCalls());
    }

    public function testEnrichAttributeCacheHitAppliedUsesAppliedValue(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 123, trackSetData: true);

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);
        $enrichment->method('getGeneratedValue')->willReturn('original');
        $enrichment->method('getAppliedValue')->willReturn('edited');

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $this->assertContains(['key' => 'description', 'value' => 'edited'], $this->getProductSetDataCalls());
    }

    public function testEnrichAttributeCacheHitPendingSkips(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 123, trackSetData: true);

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($this->getProductSetDataCalls(), fn ($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    public function testEnrichAttributeCacheHitDeniedSkips(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 123, trackSetData: true);

        $this->setUpCacheHitScenario('Describe Widget', 'somehash', 1);

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_DENIED);

        $this->enrichmentRecorder->method('findByHash')
            ->with('somehash', 'description', 1)
            ->willReturn($enrichment);

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($this->getProductSetDataCalls(), fn ($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    // --- enrichAttribute with overwrite ---

    public function testEnrichAttributeProcessesWhenOverwriteSet(): void
    {
        $product = $this->createProductMock(
            ['name' => 'Widget', 'description' => 'Existing', 'mageos_catalogai_overwrite' => true],
            storeId: 1,
            id: 123,
            trackSetData: true
        );

        $this->promptResolver->method('resolve')
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

    // --- enrichAttribute cache miss tests ---

    public function testCacheMissRecordsEnrichmentForExistingProduct(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->setUpCacheMissScenario('Describe Widget', 'hash1', 1, 'Generated text');

        $this->enrichmentRecorder->expects($this->once())
            ->method('record')
            ->with(42, 1, 'description', 'hash1', 'Describe Widget', 'Generated text');

        $this->enricher->enrichAttribute($product, 'description');
    }

    public function testCacheMissStoresDeferredEnrichmentForNewProduct(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 0, trackSetData: true);

        $this->setUpCacheMissScenario('Describe Widget', 'hash1', 1, 'Generated text');

        $this->enrichmentRecorder->expects($this->never())->method('record');

        $this->enricher->enrichAttribute($product, 'description');

        $deferredCalls = array_filter(
            $this->getProductSetDataCalls(),
            fn ($c) => $c['key'] === 'mageos_catalogai_deferred_enrichments'
        );
        $this->assertNotEmpty($deferredCalls);

        $deferred = array_values($deferredCalls)[0]['value'];
        $this->assertSame('description', $deferred[0]['attribute_code']);
        $this->assertSame('hash1', $deferred[0]['prompt_hash']);
        $this->assertSame('Generated text', $deferred[0]['generated_value']);
    }

    public function testCacheMissSetsValueWhenApprovalNotRequired(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->setUpCacheMissScenario('Describe Widget', 'hash1', 1, 'Generated text');
        $this->config->method('isApprovalRequired')->willReturn(false);

        $this->enricher->enrichAttribute($product, 'description');

        $this->assertContains(
            ['key' => 'description', 'value' => 'Generated text'],
            $this->getProductSetDataCalls()
        );
    }

    public function testCacheMissSkipsSettingValueWhenApprovalRequired(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->setUpCacheMissScenario('Describe Widget', 'hash1', 1, 'Generated text');
        $this->config->method('isApprovalRequired')->willReturn(true);

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($this->getProductSetDataCalls(), fn ($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    public function testCacheMissApiReturnsNullEarlyReturn(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->setUpCacheMissScenario('Describe Widget', 'hash1', 1, null);

        $this->enrichmentRecorder->expects($this->never())->method('record');

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($this->getProductSetDataCalls(), fn ($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    // --- enrichAttribute cache disabled tests ---

    public function testCacheDisabledApiSuccessSetsProductData(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->promptResolver->method('resolve')
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->aiClient->expects($this->once())
            ->method('generate')
            ->with('system', 'Describe Widget')
            ->willReturn('AI output');

        $this->enricher->enrichAttribute($product, 'description');

        $this->assertContains(
            ['key' => 'description', 'value' => 'AI output'],
            $this->getProductSetDataCalls()
        );
    }

    public function testCacheDisabledApiReturnsNullDoesNothing(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->promptResolver->method('resolve')
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->aiClient->expects($this->once())
            ->method('generate')
            ->willReturn(null);

        $this->enricher->enrichAttribute($product, 'description');

        $descriptionCalls = array_filter($this->getProductSetDataCalls(), fn ($c) => $c['key'] === 'description');
        $this->assertEmpty($descriptionCalls);
    }

    // --- execute tests ---

    public function testExecuteIteratesAllConfiguredAttributes(): void
    {
        $product = $this->createProductMock(
            ['name' => 'Widget', 'store_id' => 1],
            storeId: 1,
            id: 42,
            trackSetData: true
        );

        $this->config->method('getConfiguredAttributes')
            ->with(1)
            ->willReturn(['description', 'short_description']);

        $this->promptResolver->method('resolve')
            ->willReturnCallback(function (string $code) {
                return match ($code) {
                    'description' => 'Describe {{name}}',
                    'short_description' => 'Short {{name}}',
                    default => null,
                };
            });
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->aiClient->expects($this->exactly(2))
            ->method('generate')
            ->willReturn('AI text');

        $this->enricher->execute($product);

        $this->assertContains(['key' => 'description', 'value' => 'AI text'], $this->getProductSetDataCalls());
        $this->assertContains(['key' => 'short_description', 'value' => 'AI text'], $this->getProductSetDataCalls());
    }

    public function testExecuteRetriesOnErrorException(): void
    {
        $product = $this->createProductMock(
            ['name' => 'Widget', 'store_id' => 1],
            storeId: 1,
            id: 42,
            trackSetData: true
        );

        $this->config->method('getConfiguredAttributes')
            ->with(1)
            ->willReturn(['description']);

        $this->promptResolver->method('resolve')
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $errorException = (new \ReflectionClass(ErrorException::class))->newInstanceWithoutConstructor();

        $this->aiClient->expects($this->exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($errorException),
                'AI text'
            );

        $this->enricher->execute($product);

        $this->assertContains(['key' => 'description', 'value' => 'AI text'], $this->getProductSetDataCalls());
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

    private function setUpCacheHitScenario(
        string $expectedParsedPrompt,
        string $hash,
        int $storeId
    ): void {
        $this->promptResolver->method('resolve')
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->hashGenerator->method('generate')
            ->with($expectedParsedPrompt, 'system', 'description', $storeId)
            ->willReturn($hash);
    }

    private function setUpCacheMissScenario(
        string $expectedParsedPrompt,
        string $hash,
        int $storeId,
        ?string $apiReturn
    ): void {
        $this->promptResolver->method('resolve')
            ->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->hashGenerator->method('generate')
            ->with($expectedParsedPrompt, 'system', 'description', $storeId)
            ->willReturn($hash);

        $this->enrichmentRecorder->method('findByHash')
            ->with($hash, 'description', $storeId)
            ->willReturn(null);

        $this->aiClient->method('generate')
            ->with('system', $expectedParsedPrompt)
            ->willReturn($apiReturn);
    }
}
