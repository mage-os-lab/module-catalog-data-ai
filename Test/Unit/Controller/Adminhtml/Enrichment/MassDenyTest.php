<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Controller\Adminhtml\Enrichment;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment\MassDeny;
use MageOS\CatalogDataAI\Test\Unit\Trait\AdminControllerMockTrait;
use MageOS\CatalogDataAI\Test\Unit\Trait\EnrichmentMockTrait;
use MageOS\CatalogDataAI\Test\Unit\Trait\MassActionMockTrait;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MassDenyTest extends TestCase
{
    use AdminControllerMockTrait;
    use EnrichmentMockTrait;
    use MassActionMockTrait;

    private EnrichmentRepositoryInterface&MockObject $enrichmentRepository;
    private MassDeny $controller;

    protected function setUp(): void
    {
        $this->setUpAdminControllerMocks();
        $this->setUpMassActionMocks();
        $this->enrichmentRepository = $this->createMock(EnrichmentRepositoryInterface::class);

        $this->controller = new MassDeny(
            $this->context,
            $this->filter,
            $this->collectionFactory,
            $this->enrichmentRepository
        );
    }

    public function testSetsStatusDeniedOnEachRecord(): void
    {
        $enrichment1 = $this->createEnrichmentMock();
        $enrichment2 = $this->createEnrichmentMock();
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $enrichment1
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_DENIED);

        $enrichment2
            ->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_DENIED);

        $this->enrichmentRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function ($enrichment) use ($enrichment1, $enrichment2) {
                $this->assertContains($enrichment, [$enrichment1, $enrichment2]);
                return $enrichment;
            });

        $this->controller->execute();
    }

    public function testShowsSuccessCount(): void
    {
        $enrichment1 = $this->createEnrichmentMock();
        $enrichment2 = $this->createEnrichmentMock();
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->assertSuccessMessage('2');

        $this->controller->execute();
    }

    public function testHandlesPerRecordException(): void
    {
        $enrichment1 = $this->createEnrichmentMock(['entity_id' => 1]);
        $enrichment2 = $this->createEnrichmentMock(['entity_id' => 2]);
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->enrichmentRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function ($enrichment) use ($enrichment1) {
                if ($enrichment === $enrichment1) {
                    throw new LocalizedException(__('Test error'));
                }
                return $enrichment;
            });

        $this->messageManager
            ->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(fn($msg) => str_contains((string)$msg, 'Test error')));

        $this->messageManager
            ->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(fn($msg) => str_contains((string)$msg, '1')));

        $this->controller->execute();
    }

    public function testRedirectsToIndex(): void
    {
        $this->setUpFilteredCollection([]);
        $this->assertRedirectTo('*/*/index');

        $this->controller->execute();
    }

    public function testNoSuccessOnAllFail(): void
    {
        $enrichment1 = $this->createEnrichmentMock(['entity_id' => 1]);
        $enrichment2 = $this->createEnrichmentMock(['entity_id' => 2]);
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->enrichmentRepository
            ->method('save')
            ->willThrowException(new LocalizedException(__('Test error')));

        $this->messageManager
            ->expects($this->never())
            ->method('addSuccessMessage');

        $this->controller->execute();
    }
}
