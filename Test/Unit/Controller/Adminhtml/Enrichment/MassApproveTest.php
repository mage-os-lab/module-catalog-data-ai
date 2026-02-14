<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Controller\Adminhtml\Enrichment;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment\MassApprove;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;
use MageOS\CatalogDataAI\Test\Unit\Trait\AdminControllerMockTrait;
use MageOS\CatalogDataAI\Test\Unit\Trait\EnrichmentMockTrait;
use MageOS\CatalogDataAI\Test\Unit\Trait\MassActionMockTrait;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MassApproveTest extends TestCase
{
    use AdminControllerMockTrait;
    use EnrichmentMockTrait;
    use MassActionMockTrait;

    private EnrichmentApplier&MockObject $enrichmentApplier;
    private MassApprove $controller;

    protected function setUp(): void
    {
        $this->setUpAdminControllerMocks();
        $this->setUpMassActionMocks();
        $this->enrichmentApplier = $this->createMock(EnrichmentApplier::class);

        $this->controller = new MassApprove(
            $this->context,
            $this->filter,
            $this->collectionFactory,
            $this->enrichmentApplier
        );
    }

    public function testSkipsAlreadyAppliedRecords(): void
    {
        $enrichment = $this->createAppliedEnrichment();
        $this->setUpFilteredCollection([$enrichment]);

        $this->enrichmentApplier
            ->expects($this->never())
            ->method('apply');

        $this->controller->execute();
    }

    public function testAppliesNonAppliedRecords(): void
    {
        $enrichment1 = $this->createPendingEnrichment(1);
        $enrichment2 = $this->createEnrichmentMock(['status' => EnrichmentInterface::STATUS_APPROVED]);
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->enrichmentApplier
            ->expects($this->exactly(2))
            ->method('apply')
            ->willReturnCallback(function ($enrichment) use ($enrichment1, $enrichment2) {
                $this->assertContains($enrichment, [$enrichment1, $enrichment2]);
            });

        $this->controller->execute();
    }

    public function testShowsSuccessMessageWithCount(): void
    {
        $enrichment1 = $this->createPendingEnrichment(1);
        $enrichment2 = $this->createPendingEnrichment(2);
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->assertSuccessMessage('2');

        $this->controller->execute();
    }

    public function testHandlesPerRecordExceptions(): void
    {
        $enrichment1 = $this->createPendingEnrichment(1);
        $enrichment2 = $this->createPendingEnrichment(2);
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->enrichmentApplier
            ->expects($this->exactly(2))
            ->method('apply')
            ->willReturnCallback(function ($enrichment) use ($enrichment1) {
                if ($enrichment === $enrichment1) {
                    throw new LocalizedException(__('Test error'));
                }
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

    public function testNoSuccessMessageWhenAllSkipped(): void
    {
        $enrichment1 = $this->createAppliedEnrichment(1);
        $enrichment2 = $this->createAppliedEnrichment(2);
        $this->setUpFilteredCollection([$enrichment1, $enrichment2]);

        $this->messageManager
            ->expects($this->never())
            ->method('addSuccessMessage');

        $this->controller->execute();
    }
}
