<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Trait;

use Magento\Catalog\Model\Product;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Provides product mock creation helpers for unit tests.
 */
trait ProductMockTrait
{
    /** @var array<int, array{key: string, value: mixed}> */
    private array $productSetDataCalls = [];

    /**
     * Create a Product mock with configurable data, ID, and store ID.
     *
     * @param array<string, mixed> $attributes Data returned by getData()
     * @param int|null $id Product ID (falls back to $attributes['entity_id'])
     * @param int|null $storeId Store ID (falls back to $attributes['store_id'], then 0)
     * @param bool $trackSetData When true, setData() calls are recorded in $productSetDataCalls
     * @return Product&MockObject
     */
    protected function createProductMock(
        array $attributes = [],
        ?int $id = null,
        ?int $storeId = null,
        bool $trackSetData = false
    ): Product&MockObject {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $product->method('getData')
            ->willReturnCallback(function (?string $key = null) use ($attributes) {
                if ($key === null) {
                    return $attributes;
                }
                return $attributes[$key] ?? null;
            });

        $product->method('getId')->willReturn($id ?? ($attributes['entity_id'] ?? null));
        $product->method('getStoreId')->willReturn($storeId ?? ($attributes['store_id'] ?? 0));

        if ($trackSetData) {
            $this->productSetDataCalls = [];
            $product->method('setData')
                ->willReturnCallback(function (string $key, $value) use ($product) {
                    $this->productSetDataCalls[] = ['key' => $key, 'value' => $value];
                    return $product;
                });
        }

        return $product;
    }

    /**
     * @return array<int, array{key: string, value: mixed}>
     */
    protected function getProductSetDataCalls(): array
    {
        return $this->productSetDataCalls;
    }
}
