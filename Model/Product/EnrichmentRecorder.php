<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterfaceFactory;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\Config;

class EnrichmentRecorder
{
    /**
     * @param EnrichmentRepositoryInterface $enrichmentRepository
     * @param EnrichmentInterfaceFactory $enrichmentFactory
     * @param Config $config
     */
    public function __construct(
        private readonly EnrichmentRepositoryInterface $enrichmentRepository,
        private readonly EnrichmentInterfaceFactory $enrichmentFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @param string $hash
     * @param string $attributeCode
     * @param int $storeId
     * @return EnrichmentInterface|null
     */
    public function findByHash(string $hash, string $attributeCode, int $storeId): ?EnrichmentInterface
    {
        return $this->enrichmentRepository->getByHash($hash, $attributeCode, $storeId);
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param string $attributeCode
     * @param string $promptHash
     * @param string $parsedPrompt
     * @param string $generatedValue
     * @return EnrichmentInterface
     */
    public function record(
        int $productId,
        int $storeId,
        string $attributeCode,
        string $promptHash,
        string $parsedPrompt,
        string $generatedValue
    ): EnrichmentInterface {
        $enrichment = $this->enrichmentFactory->create();
        $enrichment->setProductId($productId);
        $enrichment->setStoreId($storeId);
        $enrichment->setAttributeCode($attributeCode);
        $enrichment->setPromptHash($promptHash);
        $enrichment->setParsedPrompt($parsedPrompt);
        $enrichment->setGeneratedValue($generatedValue);

        if ($this->config->isApprovalRequired()) {
            $enrichment->setStatus(EnrichmentInterface::STATUS_PENDING);
        } else {
            $enrichment->setStatus(EnrichmentInterface::STATUS_APPROVED);
        }

        return $this->enrichmentRepository->save($enrichment);
    }
}
