<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;

class EnrichmentApplier
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param EnrichmentRepositoryInterface $enrichmentRepository
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EnrichmentRepositoryInterface $enrichmentRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param EnrichmentInterface $enrichment
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply(EnrichmentInterface $enrichment): void
    {
        $storeId = $enrichment->getStoreId();
        $originalStoreId = $this->storeManager->getStore()->getId();
        $connection = $this->resourceConnection->getConnection();

        try {
            $this->storeManager->setCurrentStore($storeId);
            $connection->beginTransaction();

            $product = $this->productRepository->getById(
                $enrichment->getProductId(),
                false,
                $storeId
            );

            $value = $enrichment->getAppliedValue() ?? $enrichment->getGeneratedValue();
            $product->setData($enrichment->getAttributeCode(), $value);
            $this->productRepository->save($product);

            $enrichment->setStatus(EnrichmentInterface::STATUS_APPLIED);
            $this->enrichmentRepository->save($enrichment);

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }
}
