<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterfaceFactory;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\EnrichmentRecorder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnrichmentRecorderTest extends TestCase
{
    private EnrichmentRepositoryInterface&MockObject $enrichmentRepository;
    private EnrichmentInterfaceFactory&MockObject $enrichmentFactory;
    private Config&MockObject $config;
    private EnrichmentRecorder $enrichmentRecorder;

    protected function setUp(): void
    {
        $this->enrichmentRepository = $this->createMock(EnrichmentRepositoryInterface::class);
        $this->enrichmentFactory = $this->createMock(EnrichmentInterfaceFactory::class);
        $this->config = $this->createMock(Config::class);

        $this->enrichmentRecorder = new EnrichmentRecorder(
            $this->enrichmentRepository,
            $this->enrichmentFactory,
            $this->config
        );
    }

    public function testFindByHashDelegatesToRepository(): void
    {
        $hash = 'abc123';
        $attributeCode = 'description';
        $storeId = 1;
        $expectedEnrichment = $this->createMock(EnrichmentInterface::class);

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('getByHash')
            ->with($hash, $attributeCode, $storeId)
            ->willReturn($expectedEnrichment);

        $result = $this->enrichmentRecorder->findByHash($hash, $attributeCode, $storeId);

        $this->assertSame($expectedEnrichment, $result);
    }

    public function testFindByHashReturnsNull(): void
    {
        $hash = 'xyz789';
        $attributeCode = 'meta_title';
        $storeId = 2;

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('getByHash')
            ->with($hash, $attributeCode, $storeId)
            ->willReturn(null);

        $result = $this->enrichmentRecorder->findByHash($hash, $attributeCode, $storeId);

        $this->assertNull($result);
    }

    public function testRecordSetsStatusPendingWhenApprovalRequired(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $savedEnrichment = $this->createMock(EnrichmentInterface::class);

        $this->enrichmentFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->config
            ->expects($this->once())
            ->method('isApprovalRequired')
            ->willReturn(true);

        $enrichment
            ->expects($this->once())
            ->method('setProductId')
            ->with(42)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setStoreId')
            ->with(1)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setAttributeCode')
            ->with('description')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setPromptHash')
            ->with('hash123')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setParsedPrompt')
            ->with('parsed prompt')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setGeneratedValue')
            ->with('generated value')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_PENDING)
            ->willReturnSelf();

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willReturn($savedEnrichment);

        $this->enrichmentRecorder->record(
            42,
            1,
            'description',
            'hash123',
            'parsed prompt',
            'generated value'
        );
    }

    public function testRecordSetsStatusApprovedWhenNoApproval(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $savedEnrichment = $this->createMock(EnrichmentInterface::class);

        $this->enrichmentFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->config
            ->expects($this->once())
            ->method('isApprovalRequired')
            ->willReturn(false);

        $enrichment
            ->expects($this->once())
            ->method('setProductId')
            ->with(99)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setStoreId')
            ->with(2)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setAttributeCode')
            ->with('meta_description')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setPromptHash')
            ->with('hash456')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setParsedPrompt')
            ->with('another prompt')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setGeneratedValue')
            ->with('another value')
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_APPROVED)
            ->willReturnSelf();

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willReturn($savedEnrichment);

        $this->enrichmentRecorder->record(
            99,
            2,
            'meta_description',
            'hash456',
            'another prompt',
            'another value'
        );
    }

    public function testRecordSetsAllFields(): void
    {
        $productId = 123;
        $storeId = 3;
        $attributeCode = 'short_description';
        $promptHash = 'hash789';
        $parsedPrompt = 'test prompt';
        $generatedValue = 'test value';

        $enrichment = $this->createMock(EnrichmentInterface::class);
        $savedEnrichment = $this->createMock(EnrichmentInterface::class);

        $this->enrichmentFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->config
            ->expects($this->once())
            ->method('isApprovalRequired')
            ->willReturn(false);

        $enrichment
            ->expects($this->once())
            ->method('setProductId')
            ->with($productId)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setStoreId')
            ->with($storeId)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setAttributeCode')
            ->with($attributeCode)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setPromptHash')
            ->with($promptHash)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setParsedPrompt')
            ->with($parsedPrompt)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setGeneratedValue')
            ->with($generatedValue)
            ->willReturnSelf();

        $enrichment
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_APPROVED)
            ->willReturnSelf();

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willReturn($savedEnrichment);

        $this->enrichmentRecorder->record(
            $productId,
            $storeId,
            $attributeCode,
            $promptHash,
            $parsedPrompt,
            $generatedValue
        );
    }

    public function testRecordCallsRepositorySave(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $savedEnrichment = $this->createMock(EnrichmentInterface::class);

        $this->enrichmentFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->config
            ->expects($this->once())
            ->method('isApprovalRequired')
            ->willReturn(true);

        $enrichment
            ->method('setProductId')
            ->willReturnSelf();
        $enrichment
            ->method('setStoreId')
            ->willReturnSelf();
        $enrichment
            ->method('setAttributeCode')
            ->willReturnSelf();
        $enrichment
            ->method('setPromptHash')
            ->willReturnSelf();
        $enrichment
            ->method('setParsedPrompt')
            ->willReturnSelf();
        $enrichment
            ->method('setGeneratedValue')
            ->willReturnSelf();
        $enrichment
            ->method('setStatus')
            ->willReturnSelf();

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willReturn($savedEnrichment);

        $this->enrichmentRecorder->record(
            1,
            1,
            'test_attr',
            'hash',
            'prompt',
            'value'
        );
    }

    public function testRecordReturnsRepositoryResult(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $savedEnrichment = $this->createMock(EnrichmentInterface::class);

        $this->enrichmentFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->config
            ->expects($this->once())
            ->method('isApprovalRequired')
            ->willReturn(false);

        $enrichment
            ->method('setProductId')
            ->willReturnSelf();
        $enrichment
            ->method('setStoreId')
            ->willReturnSelf();
        $enrichment
            ->method('setAttributeCode')
            ->willReturnSelf();
        $enrichment
            ->method('setPromptHash')
            ->willReturnSelf();
        $enrichment
            ->method('setParsedPrompt')
            ->willReturnSelf();
        $enrichment
            ->method('setGeneratedValue')
            ->willReturnSelf();
        $enrichment
            ->method('setStatus')
            ->willReturnSelf();

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willReturn($savedEnrichment);

        $result = $this->enrichmentRecorder->record(
            1,
            1,
            'test_attr',
            'hash',
            'prompt',
            'value'
        );

        $this->assertSame($savedEnrichment, $result);
    }
}
