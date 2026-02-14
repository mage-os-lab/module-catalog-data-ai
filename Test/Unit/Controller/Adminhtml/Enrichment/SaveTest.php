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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment\Save;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    private Context&MockObject $context;
    private Http&MockObject $request;
    private ManagerInterface&MockObject $messageManager;
    private RedirectFactory&MockObject $resultRedirectFactory;
    private Redirect&MockObject $redirect;
    private EnrichmentRepositoryInterface&MockObject $enrichmentRepository;
    private EnrichmentApplier&MockObject $enrichmentApplier;
    private Save $controller;

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

        $this->messageManager
            ->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(fn($msg) => strpos((string)$msg, 'Invalid enrichment record') !== false));

        $this->redirect
            ->expects($this->once())
            ->method('setPath')
            ->with('*/*/index');

        $this->controller->execute();
    }

    public function testSetsAppliedValueFromPost(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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

        $this->messageManager
            ->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(fn($msg) => strpos((string)$msg, 'Invalid status value') !== false));

        $this->redirect
            ->expects($this->once())
            ->method('setPath')
            ->with('*/*/edit', ['id' => 123]);

        $this->controller->execute();
    }

    public function testAppliesWhenTransitioningToApproved(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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

        $this->messageManager
            ->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(fn($msg) => strpos((string)$msg, 'approved and applied') !== false));

        $this->controller->execute();
    }

    public function testAppliesWhenTransitioningToApplied(): void
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_APPLIED);

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
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getStatus')->willReturn(EnrichmentInterface::STATUS_PENDING);

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

        $this->messageManager
            ->expects($this->once())
            ->method('addErrorMessage')
            ->with('Test error');

        $this->redirect
            ->expects($this->once())
            ->method('setPath')
            ->with('*/*/edit', ['id' => 123]);

        $this->controller->execute();
    }
}
