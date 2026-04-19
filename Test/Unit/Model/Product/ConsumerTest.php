<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use Magento\Catalog\Model\ProductRepository;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\CatalogDataAI\Model\Product\Consumer;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\Request;
use MageOS\CatalogDataAI\Test\Unit\Trait\ProductMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConsumerTest extends TestCase
{
    use ProductMockTrait;

    private Enricher&MockObject $enricher;
    private ProductRepository&MockObject $productRepository;
    private StoreManagerInterface&MockObject $storeManager;
    private Consumer $consumer;

    protected function setUp(): void
    {
        $this->enricher = $this->createMock(Enricher::class);
        $this->productRepository = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getById', 'save'])
            ->getMock();
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->consumer = new Consumer(
            $this->enricher,
            $this->productRepository,
            $this->storeManager
        );
    }

    /**
     * @return void
     */
    public function testExecuteSetsStoreToZero(): void
    {
        $request = $this->createRequestMock(42, false);
        $product = $this->createProductMock();
        $product->method('hasDataChanges')->willReturn(false);

        $this->productRepository->method('getById')->willReturn($product);

        $this->storeManager->expects($this->once())
            ->method('setCurrentStore')
            ->with(0);

        $this->consumer->execute($request);
    }

    /**
     * @return void
     */
    public function testExecuteLoadsProductById(): void
    {
        $productId = 42;
        $request = $this->createRequestMock($productId, false);
        $product = $this->createProductMock();
        $product->method('hasDataChanges')->willReturn(false);

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->with($productId)
            ->willReturn($product);

        $this->consumer->execute($request);
    }

    /**
     * @return void
     */
    public function testExecuteSetsOverwriteFlag(): void
    {
        $overwrite = true;
        $request = $this->createRequestMock(42, $overwrite);
        $product = $this->createProductMock();
        $product->method('hasDataChanges')->willReturn(false);

        $this->productRepository->method('getById')->willReturn($product);

        $product->expects($this->once())
            ->method('setData')
            ->with('mageos_catalogai_overwrite', $overwrite);

        $this->consumer->execute($request);
    }

    /**
     * @return void
     */
    public function testExecuteSavesProductWhenChanged(): void
    {
        $request = $this->createRequestMock(42, false);
        $product = $this->createProductMock();
        $product->method('hasDataChanges')->willReturn(true);

        $this->productRepository->method('getById')->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->consumer->execute($request);
    }

    /**
     * @return void
     */
    public function testExecuteDoesNotSaveWhenUnchanged(): void
    {
        $request = $this->createRequestMock(42, false);
        $product = $this->createProductMock();
        $product->method('hasDataChanges')->willReturn(false);

        $this->productRepository->method('getById')->willReturn($product);

        $this->productRepository->expects($this->never())
            ->method('save');

        $this->consumer->execute($request);
    }

    /**
     * @param int $id
     * @param bool $overwrite
     * @return Request&MockObject
     */
    private function createRequestMock(int $id, bool $overwrite): Request&MockObject
    {
        $request = $this->createMock(Request::class);
        $request->method('getId')->willReturn($id);
        $request->method('getOverwrite')->willReturn($overwrite);
        return $request;
    }
}
