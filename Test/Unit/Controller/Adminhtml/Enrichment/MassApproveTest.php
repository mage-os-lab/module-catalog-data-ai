<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Controller\Adminhtml\Enrichment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment\MassApprove;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\CollectionFactory;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MassApproveTest extends TestCase
{
    private Context&MockObject $context;
    private Http&MockObject $request;
    private ManagerInterface&MockObject $messageManager;
    private RedirectFactory&MockObject $resultRedirectFactory;
    private Redirect&MockObject $redirect;
    private Filter&MockObject $filter;
    private CollectionFactory&MockObject $collectionFactory;
    private EnrichmentApplier&MockObject $enrichmentApplier;
    private MassApprove $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->resultRedirectFactory = $this->createMock(RedirectFactory::class);

        $this->resultRedirectFactory
            ->method('create')
            ->willReturn($this->redirect);

        $this->redirect
            ->method('setPath')
            ->willReturnSelf();

        $this->context = $this->createMock(Context::class);
        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getMessageManager')->willReturn($this->messageManager);
        $this->context->method('getResultRedirectFactory')->willReturn($this->resultRedirectFactory);

        $this->filter = $this->createMock(Filter::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
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
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);

        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$enrichment]));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->enrichmentApplier
            ->expects($this->never())
            ->method('apply');

        $this->controller->execute();
    }

    public function testAppliesNonAppliedRecords(): void
    {
        $enrichment1 = $this->createMock(EnrichmentInterface::class);
        $enrichment1->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

        $enrichment2 = $this->createMock(EnrichmentInterface::class);
        $enrichment2->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPROVED);

        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$enrichment1, $enrichment2]));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

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
        $enrichment1 = $this->createMock(EnrichmentInterface::class);
        $enrichment1->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

        $enrichment2 = $this->createMock(EnrichmentInterface::class);
        $enrichment2->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$enrichment1, $enrichment2]));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->messageManager
            ->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(function ($msg) {
                $msgStr = (string)$msg;
                return strpos($msgStr, '2') !== false && strpos($msgStr, 'approved and applied') !== false;
            }));

        $this->controller->execute();
    }

    public function testHandlesPerRecordExceptions(): void
    {
        $enrichment1 = $this->createMock(EnrichmentInterface::class);
        $enrichment1->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);
        $enrichment1->method('getEntityId')->willReturn(1);

        $enrichment2 = $this->createMock(EnrichmentInterface::class);
        $enrichment2->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);
        $enrichment2->method('getEntityId')->willReturn(2);

        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$enrichment1, $enrichment2]));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

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
            ->with($this->callback(fn($msg) => strpos((string)$msg, 'Test error') !== false));

        $this->messageManager
            ->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(fn($msg) => strpos((string)$msg, '1') !== false));

        $this->controller->execute();
    }

    public function testRedirectsToIndex(): void
    {
        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->redirect
            ->expects($this->once())
            ->method('setPath')
            ->with('*/*/index');

        $this->controller->execute();
    }

    public function testNoSuccessMessageWhenAllSkipped(): void
    {
        $enrichment1 = $this->createMock(EnrichmentInterface::class);
        $enrichment1->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);

        $enrichment2 = $this->createMock(EnrichmentInterface::class);
        $enrichment2->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);

        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$enrichment1, $enrichment2]));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->messageManager
            ->expects($this->never())
            ->method('addSuccessMessage');

        $this->controller->execute();
    }
}
