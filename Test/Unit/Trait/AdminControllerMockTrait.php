<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Trait;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Provides admin controller mock setup for unit tests.
 *
 * Sets up: Context, Http Request, MessageManager, RedirectFactory, Redirect.
 */
trait AdminControllerMockTrait
{
    protected Context&MockObject $context;
    protected Http&MockObject $request;
    protected ManagerInterface&MockObject $messageManager;
    protected RedirectFactory&MockObject $resultRedirectFactory;
    protected Redirect&MockObject $redirect;

    /**
     * Create and configure admin controller mock dependencies.
     */
    protected function setUpAdminControllerMocks(): void
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
    }

    /**
     * Assert a success message containing the given substring was added.
     */
    protected function assertSuccessMessage(string $substring): void
    {
        $this->messageManager
            ->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(fn($msg) => str_contains((string)$msg, $substring)));
    }

    /**
     * Assert an error message containing the given substring was added.
     */
    protected function assertErrorMessage(string $substring): void
    {
        $this->messageManager
            ->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(fn($msg) => str_contains((string)$msg, $substring)));
    }

    /**
     * Assert a redirect to the given path (with optional params) was set.
     */
    protected function assertRedirectTo(string $path, array $params = []): void
    {
        $this->redirect
            ->expects($this->once())
            ->method('setPath')
            ->with($path, ...($params ? [$params] : []));
    }
}
