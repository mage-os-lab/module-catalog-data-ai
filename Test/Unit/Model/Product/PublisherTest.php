<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use Magento\Framework\MessageQueue\PublisherInterface;
use MageOS\CatalogDataAI\Model\Product\Publisher;
use MageOS\CatalogDataAI\Model\Product\Request;
use MageOS\CatalogDataAI\Model\Product\RequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    private PublisherInterface&MockObject $messagePublisher;
    private RequestFactory&MockObject $requestFactory;
    private Publisher $publisher;

    protected function setUp(): void
    {
        $this->messagePublisher = $this->createMock(PublisherInterface::class);
        $this->requestFactory = $this->createMock(RequestFactory::class);
        $this->publisher = new Publisher($this->messagePublisher, $this->requestFactory);
    }

    /**
     * @return void
     */
    public function testExecuteCreatesRequestWithCorrectParams(): void
    {
        $productId = 42;
        $overwrite = false;
        $request = $this->createMock(Request::class);

        $this->requestFactory->expects($this->once())
            ->method('create')
            ->with([
                'id' => $productId,
                'overwrite' => $overwrite
            ])
            ->willReturn($request);

        $this->messagePublisher->method('publish');

        $this->publisher->execute($productId, $overwrite);
    }

    /**
     * @return void
     */
    public function testExecutePublishesToCorrectTopic(): void
    {
        $productId = 42;
        $request = $this->createMock(Request::class);

        $this->requestFactory->method('create')->willReturn($request);

        $this->messagePublisher->expects($this->once())
            ->method('publish')
            ->with(Publisher::TOPIC_NAME, $request);

        $this->publisher->execute($productId);
    }

    /**
     * @return void
     */
    public function testExecuteDefaultOverwriteIsFalse(): void
    {
        $productId = 42;
        $request = $this->createMock(Request::class);

        $this->requestFactory->expects($this->once())
            ->method('create')
            ->with([
                'id' => $productId,
                'overwrite' => false
            ])
            ->willReturn($request);

        $this->messagePublisher->method('publish');

        $this->publisher->execute($productId);
    }

    /**
     * @return void
     */
    public function testExecuteCastsStringIdToInt(): void
    {
        $productId = '42';
        $overwrite = true;
        $request = $this->createMock(Request::class);

        $this->requestFactory->expects($this->once())
            ->method('create')
            ->with([
                'id' => 42,
                'overwrite' => $overwrite
            ])
            ->willReturn($request);

        $this->messagePublisher->method('publish');

        $this->publisher->execute($productId, $overwrite);
    }
}
