<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Observer\Product\SaveBefore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveBeforeTest extends TestCase
{
    private Config&MockObject $config;
    private Enricher&MockObject $enricher;
    private SaveBefore $observer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->enricher = $this->createMock(Enricher::class);
        $this->observer = new SaveBefore($this->config, $this->enricher);
    }

    public function testExecuteCallsEnricherWhenSyncAndCanEnrich(): void
    {
        $product = $this->createProductMock();
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(true);
        $this->config->method('isAsync')->willReturn(false);

        $this->enricher->expects($this->once())->method('execute')->with($product);

        $this->observer->execute($event);
    }

    public function testExecuteSkipsWhenAsync(): void
    {
        $product = $this->createProductMock();
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(true);
        $this->config->method('isAsync')->willReturn(true);

        $this->enricher->expects($this->never())->method('execute');

        $this->observer->execute($event);
    }

    public function testExecuteSkipsWhenCannotEnrich(): void
    {
        $product = $this->createProductMock();
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(false);

        $this->enricher->expects($this->never())->method('execute');

        $this->observer->execute($event);
    }

    public function testExecuteSkipsWhenBothDisabled(): void
    {
        $product = $this->createProductMock();
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(false);
        $this->config->method('isAsync')->willReturn(true);

        $this->enricher->expects($this->never())->method('execute');

        $this->observer->execute($event);
    }

    private function createProductMock(): Product&MockObject
    {
        return $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createObserver(Product&MockObject $product): Observer
    {
        $event = new Event(['product' => $product]);
        $observer = new Observer(['event' => $event, 'product' => $product]);
        return $observer;
    }
}
