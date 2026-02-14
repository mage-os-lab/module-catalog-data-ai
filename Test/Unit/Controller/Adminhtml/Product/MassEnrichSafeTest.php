<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Controller\Adminhtml\Product;

use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Controller\Adminhtml\Product\MassEnrichSafe;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Publisher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MassEnrichSafeTest extends TestCase
{
    private Context&MockObject $context;
    private Http&MockObject $request;
    private ManagerInterface&MockObject $messageManager;
    private ResultFactory&MockObject $resultFactory;
    private Redirect&MockObject $redirect;
    private Filter&MockObject $filter;
    private CollectionFactory&MockObject $collectionFactory;
    private Config&MockObject $config;
    private Publisher&MockObject $publisher;
    private ProductRepositoryInterface&MockObject $productRepository;
    private MassEnrichSafe $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);

        $this->resultFactory
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($this->redirect);

        $this->redirect
            ->method('setPath')
            ->willReturnSelf();

        $this->context = $this->createMock(Context::class);
        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getMessageManager')->willReturn($this->messageManager);
        $this->context->method('getResultFactory')->willReturn($this->resultFactory);

        $this->filter = $this->createMock(Filter::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->config = $this->createMock(Config::class);
        $this->publisher = $this->createMock(Publisher::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);

        $this->controller = new MassEnrichSafe(
            $this->context,
            $this->filter,
            $this->collectionFactory,
            $this->config,
            $this->publisher,
            $this->productRepository
        );
    }

    public function testOverwritePropertyIsFalse(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);

        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([$product]);

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->config
            ->method('isEnabled')
            ->willReturn(true);

        $this->publisher
            ->expects($this->once())
            ->method('execute')
            ->with(1, false);

        $this->controller->execute();
    }
}
