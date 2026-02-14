<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;
use MageOS\CatalogDataAI\Test\Unit\Trait\EnrichmentMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnrichmentApplierTest extends TestCase
{
    use EnrichmentMockTrait;

    private ProductRepositoryInterface&MockObject $productRepository;
    private EnrichmentRepositoryInterface&MockObject $enrichmentRepository;
    private StoreManagerInterface&MockObject $storeManager;
    private EnrichmentApplier $enrichmentApplier;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->enrichmentRepository = $this->createMock(EnrichmentRepositoryInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->enrichmentApplier = new EnrichmentApplier(
            $this->productRepository,
            $this->enrichmentRepository,
            $this->storeManager
        );
    }

    /**
     * @return void
     */
    public function testApplySwitchesStoreContext(): void
    {
        $storeId = 2;
        $originalStoreId = 1;

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 123,
            'attribute_code' => 'description',
            'generated_value' => 'generated',
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore')
            ->willReturnCallback(function (int $id) use ($storeId, $originalStoreId) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals($storeId, $id);
                } elseif ($callCount === 2) {
                    $this->assertEquals($originalStoreId, $id);
                }
            });

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->with(123, false, $storeId)
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->enrichmentRepository->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->enrichmentApplier->apply($enrichment);
    }

    /**
     * @return void
     */
    public function testApplyRestoresStoreAfterSuccess(): void
    {
        $storeId = 3;
        $originalStoreId = 1;

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 456,
            'attribute_code' => 'short_description',
            'generated_value' => 'value',
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $callSequence = [];
        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore')
            ->willReturnCallback(function (int $id) use (&$callSequence) {
                $callSequence[] = $id;
            });

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save');

        $this->enrichmentRepository->expects($this->once())
            ->method('save');

        $this->enrichmentApplier->apply($enrichment);

        $this->assertSame([$storeId, $originalStoreId], $callSequence);
    }

    /**
     * @return void
     */
    public function testApplyRestoresStoreOnException(): void
    {
        $storeId = 4;
        $originalStoreId = 1;

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 789,
            'attribute_code' => 'meta_description',
            'generated_value' => 'generated',
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $callSequence = [];
        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore')
            ->willReturnCallback(function (int $id) use (&$callSequence) {
                $callSequence[] = $id;
            });

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Save failed'));

        try {
            $this->enrichmentApplier->apply($enrichment);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Save failed', $e->getMessage());
        }

        $this->assertSame([$storeId, $originalStoreId], $callSequence);
    }

    /**
     * @return void
     */
    public function testApplyUsesAppliedValueWhenPresent(): void
    {
        $storeId = 1;
        $originalStoreId = 1;
        $attributeCode = 'description';
        $appliedValue = 'edited by admin';

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 111,
            'attribute_code' => $attributeCode,
            'generated_value' => 'generated',
            'applied_value' => $appliedValue,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore');

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $product->expects($this->once())
            ->method('setData')
            ->with($attributeCode, $appliedValue);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->enrichmentRepository->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->enrichmentApplier->apply($enrichment);
    }

    /**
     * @return void
     */
    public function testApplyFallsBackToGeneratedValue(): void
    {
        $storeId = 1;
        $originalStoreId = 1;
        $attributeCode = 'meta_title';
        $generatedValue = 'AI generated title';

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 222,
            'attribute_code' => $attributeCode,
            'generated_value' => $generatedValue,
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore');

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $product->expects($this->once())
            ->method('setData')
            ->with($attributeCode, $generatedValue);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->enrichmentRepository->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->enrichmentApplier->apply($enrichment);
    }

    /**
     * @return void
     */
    public function testApplySavesProduct(): void
    {
        $storeId = 1;
        $originalStoreId = 1;

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 333,
            'attribute_code' => 'description',
            'generated_value' => 'value',
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore');

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->enrichmentRepository->expects($this->once())
            ->method('save');

        $this->enrichmentApplier->apply($enrichment);
    }

    /**
     * @return void
     */
    public function testApplySetsStatusApplied(): void
    {
        $storeId = 1;
        $originalStoreId = 1;

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 444,
            'attribute_code' => 'meta_keywords',
            'generated_value' => 'keywords',
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore');

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save');

        $enrichment->expects($this->once())
            ->method('setStatus')
            ->with(EnrichmentInterface::STATUS_APPLIED);

        $this->enrichmentRepository->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->enrichmentApplier->apply($enrichment);
    }

    /**
     * @return void
     */
    public function testApplySavesEnrichment(): void
    {
        $storeId = 1;
        $originalStoreId = 1;

        $enrichment = $this->createEnrichmentMock([
            'store_id' => $storeId,
            'product_id' => 555,
            'attribute_code' => 'short_description',
            'generated_value' => 'content',
            'applied_value' => null,
        ]);
        $store = $this->createStoreMock($originalStoreId);
        $product = $this->createMock(Product::class);

        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this->storeManager->expects($this->exactly(2))
            ->method('setCurrentStore');

        $this->productRepository->expects($this->once())
            ->method('getById')
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save');

        $this->enrichmentRepository->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $this->enrichmentApplier->apply($enrichment);
    }

    /**
     * @param int $storeId
     * @return StoreInterface&MockObject
     */
    private function createStoreMock(int $storeId): StoreInterface&MockObject
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }
}
