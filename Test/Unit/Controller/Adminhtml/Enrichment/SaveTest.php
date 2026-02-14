<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Controller\Adminhtml\Enrichment;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment\Save;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;
use MageOS\CatalogDataAI\Test\Unit\Trait\AdminControllerMockTrait;
use MageOS\CatalogDataAI\Test\Unit\Trait\EnrichmentMockTrait;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    use AdminControllerMockTrait;
    use EnrichmentMockTrait;

    private EnrichmentRepositoryInterface&MockObject $enrichmentRepository;
    private EnrichmentApplier&MockObject $enrichmentApplier;
    private Save $controller;

    protected function setUp(): void
    {
        $this->setUpAdminControllerMocks();

        $this->enrichmentRepository = $this->createMock(EnrichmentRepositoryInterface::class);
        $this->enrichmentApplier = $this->createMock(EnrichmentApplier::class);

        $this->controller = new Save(
            $this->context,
            $this->enrichmentRepository,
            $this->enrichmentApplier
        );
    }

    public function testReturnsErrorWhenNoEntityId(): void
    {
        $this->request
            ->method('getPostValue')
            ->willReturn([]);

        $this->assertErrorMessage('Invalid enrichment record');
        $this->assertRedirectTo('*/*/index');

        $this->controller->execute();
    }

    public function testSetsAppliedValueFromPost(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'applied_value' => 'edited',
                'status' => EnrichmentInterface::STATUS_PENDING,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $enrichment
            ->expects($this->once())
            ->method('setAppliedValue')
            ->with('edited');

        $enrichment
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_PENDING);

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->controller->execute();
    }

    public function testConvertsEmptyAppliedValueToNull(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'applied_value' => '',
                'status' => EnrichmentInterface::STATUS_PENDING,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $enrichment
            ->expects($this->once())
            ->method('setAppliedValue')
            ->with(null);

        $this->controller->execute();
    }

    public function testSetsAdminNotes(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'admin_notes' => 'note',
                'status' => EnrichmentInterface::STATUS_PENDING,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $enrichment
            ->expects($this->once())
            ->method('setAdminNotes')
            ->with('note');

        $this->controller->execute();
    }

    public function testRejectsInvalidStatus(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'status' => 'invalid',
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $this->assertErrorMessage('Invalid status value');
        $this->assertRedirectTo('*/*/edit', ['id' => 123]);

        $this->controller->execute();
    }

    public function testAppliesWhenTransitioningToApproved(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'status' => EnrichmentInterface::STATUS_APPROVED,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $this->enrichmentApplier
            ->expects($this->once())
            ->method('apply')
            ->with($enrichment);

        $this->assertSuccessMessage('approved and applied');

        $this->controller->execute();
    }

    public function testAppliesWhenTransitioningToApplied(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'status' => EnrichmentInterface::STATUS_APPLIED,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $this->enrichmentApplier
            ->expects($this->once())
            ->method('apply')
            ->with($enrichment);

        $this->controller->execute();
    }

    public function testDoesNotReApplyAlreadyApplied(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_APPLIED]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'status' => EnrichmentInterface::STATUS_APPLIED,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $this->enrichmentApplier
            ->expects($this->never())
            ->method('apply');

        $enrichment
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_APPLIED);

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->controller->execute();
    }

    public function testSavesWithoutApplyForDenied(): void
    {
        $enrichment = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_PENDING]);

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
                'status' => EnrichmentInterface::STATUS_DENIED,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willReturn($enrichment);

        $this->enrichmentApplier
            ->expects($this->never())
            ->method('apply');

        $enrichment
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_DENIED);

        $this->enrichmentRepository
            ->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->controller->execute();
    }

    public function testHandlesLocalizedException(): void
    {
        $exception = new LocalizedException(__('Test error'));

        $this->request
            ->method('getPostValue')
            ->willReturn([
                'entity_id' => 123,
            ]);

        $this->enrichmentRepository
            ->method('getById')
            ->with(123)
            ->willThrowException($exception);

        $this->assertErrorMessage('Test error');
        $this->assertRedirectTo('*/*/edit', ['id' => 123]);

        $this->controller->execute();
    }
}
