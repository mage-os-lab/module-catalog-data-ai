<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Api\AiClientInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\ProductContextBuilderInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\EnrichmentRecorder;
use MageOS\CatalogDataAI\Model\Product\HashGenerator;
use MageOS\CatalogDataAI\Test\Unit\Trait\ProductMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnricherTest extends TestCase
{
    use ProductMockTrait;

    private AiClientInterface&MockObject $aiClient;
    private Config&MockObject $config;
    private HashGenerator&MockObject $hashGenerator;
    private EnrichmentRecorder&MockObject $enrichmentRecorder;
    private ProductContextBuilderInterface&MockObject $contextBuilder;
    private Enricher $enricher;

    protected function setUp(): void
    {
        $this->aiClient = $this->createMock(AiClientInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->hashGenerator = $this->createMock(HashGenerator::class);
        $this->enrichmentRecorder = $this->createMock(EnrichmentRecorder::class);
        $this->contextBuilder = $this->createMock(ProductContextBuilderInterface::class);

        $this->enricher = new Enricher(
            $this->aiClient,
            $this->config,
            $this->hashGenerator,
            $this->enrichmentRecorder,
            $this->contextBuilder
        );
    }

    // --- parsePrompt tests (unchanged public method) ---

    public function testParsePromptReplacesPlaceholders(): void
    {
        $product = $this->createProductMock(['name' => 'Widget']);
        $this->assertSame('Describe Widget', $this->enricher->parsePrompt('Describe {{name}}', $product));
    }

    public function testParsePromptNullAttributeReturnsEmptyString(): void
    {
        $product = $this->createProductMock([]);
        $this->assertSame('', $this->enricher->parsePrompt('{{missing}}', $product));
    }

    // --- getAttributes ---

    public function testGetAttributesDelegatesToConfig(): void
    {
        $this->config->expects($this->once())
            ->method('getConfiguredAttributes')
            ->with(5)
            ->willReturn(['description']);

        $this->assertSame(['description'], $this->enricher->getAttributes(5));
    }

    // --- execute: nothing to do ---

    public function testExecuteNoAttributesConfigured(): void
    {
        $product = $this->createProductMock([], storeId: 1);
        $this->config->method('getConfiguredAttributes')->willReturn([]);

        $this->aiClient->expects($this->never())->method('generateBatch');
        $this->aiClient->expects($this->never())->method('generate');

        $this->enricher->execute($product);
    }

    public function testExecuteAllAttributesHaveValues(): void
    {
        $product = $this->createProductMock(
            ['description' => 'Existing', 'meta_title' => 'Existing Title'],
            storeId: 1
        );
        $this->config->method('getConfiguredAttributes')->willReturn(['description', 'meta_title']);

        $this->aiClient->expects($this->never())->method('generateBatch');

        $this->enricher->execute($product);
    }

    // --- execute: full batch (cache disabled) ---

    public function testExecuteBatchCacheDisabled(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->with(1)->willReturn(['description', 'meta_title']);
        $this->config->method('getProductPrompt')->willReturnMap([
            ['description', 1, 'Describe {{name}}'],
            ['meta_title', 1, 'Title for {{name}}'],
        ]);
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(false);

        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        $this->aiClient->expects($this->once())
            ->method('generateBatch')
            ->with('system', 'Name: Widget', [
                'description' => 'Describe Widget',
                'meta_title' => 'Title for Widget',
            ])
            ->willReturn([
                'description' => 'AI description',
                'meta_title' => 'AI title',
            ]);

        $this->aiClient->expects($this->never())->method('generate');

        $this->enricher->execute($product);

        $calls = $this->getProductSetDataCalls();
        $this->assertContains(['key' => 'description', 'value' => 'AI description'], $calls);
        $this->assertContains(['key' => 'meta_title', 'value' => 'AI title'], $calls);
    }

    // --- execute: full batch (cache enabled, all miss) ---

    public function testExecuteBatchCacheEnabledAllMiss(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description']);
        $this->config->method('getProductPrompt')->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(false);

        $this->hashGenerator->method('generate')->willReturn('hash1');
        $this->enrichmentRecorder->method('findByHash')->willReturn(null);
        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        $this->aiClient->method('generateBatch')->willReturn(['description' => 'AI text']);

        $this->enrichmentRecorder->expects($this->once())
            ->method('record')
            ->with(42, 1, 'description', 'hash1', 'Describe Widget', 'AI text');

        $this->enricher->execute($product);

        $this->assertContains(['key' => 'description', 'value' => 'AI text'], $this->getProductSetDataCalls());
    }

    // --- execute: partial cache hit ---

    public function testExecutePartialCacheHit(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description', 'meta_title']);
        $this->config->method('getProductPrompt')->willReturnMap([
            ['description', 1, 'Describe {{name}}'],
            ['meta_title', 1, 'Title for {{name}}'],
        ]);
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(false);

        $this->hashGenerator->method('generate')
            ->willReturnMap([
                ['Describe Widget', 'system', 'description', 1, 'hash_desc'],
                ['Title for Widget', 'system', 'meta_title', 1, 'hash_meta'],
            ]);

        // description is cached (approved), meta_title is not
        $cachedEnrichment = $this->createMock(EnrichmentInterface::class);
        $cachedEnrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPROVED);
        $cachedEnrichment->method('getGeneratedValue')->willReturn('Cached description');
        $cachedEnrichment->method('getAppliedValue')->willReturn(null);

        $this->enrichmentRecorder->method('findByHash')
            ->willReturnMap([
                ['hash_desc', 'description', 1, $cachedEnrichment],
                ['hash_meta', 'meta_title', 1, null],
            ]);

        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        // Batch should only contain meta_title
        $this->aiClient->expects($this->once())
            ->method('generateBatch')
            ->with('system', 'Name: Widget', ['meta_title' => 'Title for Widget'])
            ->willReturn(['meta_title' => 'AI title']);

        $this->enricher->execute($product);

        $calls = $this->getProductSetDataCalls();
        $this->assertContains(['key' => 'description', 'value' => 'Cached description'], $calls);
        $this->assertContains(['key' => 'meta_title', 'value' => 'AI title'], $calls);
    }

    // --- execute: full cache hit ---

    public function testExecuteFullCacheHitNoApiCalls(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description']);
        $this->config->method('getProductPrompt')->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->hashGenerator->method('generate')->willReturn('hash1');

        $cached = $this->createMock(EnrichmentInterface::class);
        $cached->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);
        $cached->method('getGeneratedValue')->willReturn('original');
        $cached->method('getAppliedValue')->willReturn('edited');

        $this->enrichmentRecorder->method('findByHash')->willReturn($cached);

        $this->aiClient->expects($this->never())->method('generateBatch');
        $this->aiClient->expects($this->never())->method('generate');

        $this->enricher->execute($product);

        $this->assertContains(['key' => 'description', 'value' => 'edited'], $this->getProductSetDataCalls());
    }

    // --- execute: cache hit with pending/denied status ---

    public function testExecuteCacheHitPendingDoesNotApply(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description']);
        $this->config->method('getProductPrompt')->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');

        $this->hashGenerator->method('generate')->willReturn('hash1');

        $pending = $this->createMock(EnrichmentInterface::class);
        $pending->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

        $this->enrichmentRecorder->method('findByHash')->willReturn($pending);

        $this->aiClient->expects($this->never())->method('generateBatch');

        $this->enricher->execute($product);

        $descCalls = array_filter($this->getProductSetDataCalls(), fn($c) => $c['key'] === 'description');
        $this->assertEmpty($descCalls);
    }

    // --- execute: batch fallback ---

    public function testExecuteFallsBackToIndividualCallsOnBatchFailure(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description', 'meta_title']);
        $this->config->method('getProductPrompt')->willReturnMap([
            ['description', 1, 'Describe {{name}}'],
            ['meta_title', 1, 'Title for {{name}}'],
        ]);
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(false);
        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        // Batch returns empty = failure
        $this->aiClient->method('generateBatch')->willReturn([]);

        // Fallback to individual calls
        $this->aiClient->expects($this->exactly(2))
            ->method('generate')
            ->willReturnMap([
                ['system', 'Describe Widget', 'Fallback description'],
                ['system', 'Title for Widget', 'Fallback title'],
            ]);

        $this->enricher->execute($product);

        $calls = $this->getProductSetDataCalls();
        $this->assertContains(['key' => 'description', 'value' => 'Fallback description'], $calls);
        $this->assertContains(['key' => 'meta_title', 'value' => 'Fallback title'], $calls);
    }

    // --- execute: approval required ---

    public function testExecuteApprovalRequiredDoesNotSetProductValues(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 42, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description']);
        $this->config->method('getProductPrompt')->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(true);

        $this->hashGenerator->method('generate')->willReturn('hash1');
        $this->enrichmentRecorder->method('findByHash')->willReturn(null);
        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        $this->aiClient->method('generateBatch')->willReturn(['description' => 'AI text']);

        $this->enrichmentRecorder->expects($this->once())->method('record');

        $this->enricher->execute($product);

        $descCalls = array_filter($this->getProductSetDataCalls(), fn($c) => $c['key'] === 'description');
        $this->assertEmpty($descCalls);
    }

    // --- execute: deferred enrichment for new products ---

    public function testExecuteNewProductStoresDeferredEnrichment(): void
    {
        $product = $this->createProductMock(['name' => 'Widget'], storeId: 1, id: 0, trackSetData: true);

        $this->config->method('getConfiguredAttributes')->willReturn(['description']);
        $this->config->method('getProductPrompt')->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(false);

        $this->hashGenerator->method('generate')->willReturn('hash1');
        $this->enrichmentRecorder->method('findByHash')->willReturn(null);
        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        $this->aiClient->method('generateBatch')->willReturn(['description' => 'AI text']);

        $this->enrichmentRecorder->expects($this->never())->method('record');

        $this->enricher->execute($product);

        $deferredCalls = array_filter(
            $this->getProductSetDataCalls(),
            fn($c) => $c['key'] === 'mageos_catalogai_deferred_enrichments'
        );
        $this->assertNotEmpty($deferredCalls);

        $deferred = array_values($deferredCalls)[0]['value'];
        $this->assertSame('description', $deferred[0]['attribute_code']);
        $this->assertSame('hash1', $deferred[0]['prompt_hash']);
        $this->assertSame('AI text', $deferred[0]['generated_value']);
    }

    // --- execute: overwrite flag ---

    public function testExecuteOverwriteProcessesExistingValues(): void
    {
        $product = $this->createProductMock(
            ['name' => 'Widget', 'description' => 'Old text', 'mageos_catalogai_overwrite' => true],
            storeId: 1,
            id: 42,
            trackSetData: true
        );

        $this->config->method('getConfiguredAttributes')->willReturn(['description']);
        $this->config->method('getProductPrompt')->willReturn('Describe {{name}}');
        $this->config->method('isCacheEnabled')->willReturn(false);
        $this->config->method('getSystemPrompt')->willReturn('system');
        $this->config->method('isApprovalRequired')->willReturn(false);
        $this->contextBuilder->method('build')->willReturn('Name: Widget');

        $this->aiClient->expects($this->once())
            ->method('generateBatch')
            ->willReturn(['description' => 'New AI text']);

        $this->enricher->execute($product);

        $this->assertContains(
            ['key' => 'description', 'value' => 'New AI text'],
            $this->getProductSetDataCalls()
        );
    }
}
