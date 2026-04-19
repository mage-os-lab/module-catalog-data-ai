<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterface;

interface EnrichmentRepositoryInterface
{
    /**
     * @param EnrichmentInterface $enrichment
     * @return EnrichmentInterface
     * @throws CouldNotSaveException
     */
    public function save(EnrichmentInterface $enrichment): EnrichmentInterface;

    /**
     * @param int $id
     * @return EnrichmentInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): EnrichmentInterface;

    /**
     * @param string $hash
     * @param string $attributeCode
     * @param int $storeId
     * @return EnrichmentInterface|null
     */
    public function getByHash(string $hash, string $attributeCode, int $storeId): ?EnrichmentInterface;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return EnrichmentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): EnrichmentSearchResultsInterface;

    /**
     * @param EnrichmentInterface $enrichment
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(EnrichmentInterface $enrichment): bool;
}
