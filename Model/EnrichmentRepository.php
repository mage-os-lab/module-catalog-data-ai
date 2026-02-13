<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterfaceFactory;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\EnrichmentFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment as EnrichmentResource;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class EnrichmentRepository implements EnrichmentRepositoryInterface
{
    /**
     * @param EnrichmentFactory $enrichmentFactory
     * @param EnrichmentResource $resource
     * @param CollectionFactory $collectionFactory
     * @param EnrichmentSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly EnrichmentFactory $enrichmentFactory,
        private readonly EnrichmentResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @param EnrichmentInterface $enrichment
     * @return EnrichmentInterface
     * @throws CouldNotSaveException
     */
    public function save(EnrichmentInterface $enrichment): EnrichmentInterface
    {
        try {
            $this->resource->save($enrichment);
        } catch (DuplicateException $e) {
            $existing = $this->getByHash(
                $enrichment->getPromptHash(),
                $enrichment->getAttributeCode(),
                $enrichment->getStoreId()
            );
            if ($existing !== null) {
                return $existing;
            }
            throw new CouldNotSaveException(__('Could not save enrichment: %1', $e->getMessage()), $e);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save enrichment: %1', $e->getMessage()), $e);
        }

        return $enrichment;
    }

    /**
     * @param int $id
     * @return EnrichmentInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): EnrichmentInterface
    {
        $enrichment = $this->enrichmentFactory->create();
        $this->resource->load($enrichment, $id);

        if ($enrichment->getEntityId() === null) {
            throw new NoSuchEntityException(__('Enrichment with ID "%1" does not exist.', $id));
        }

        return $enrichment;
    }

    /**
     * @param string $hash
     * @param string $attributeCode
     * @param int $storeId
     * @return EnrichmentInterface|null
     */
    public function getByHash(string $hash, string $attributeCode, int $storeId): ?EnrichmentInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('prompt_hash', $hash)
            ->addFieldToFilter('attribute_code', $attributeCode)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getEntityId() !== null ? $item : null;
    }

    /**
     * @param SearchCriteriaInterface $criteria
     * @return EnrichmentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): EnrichmentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param EnrichmentInterface $enrichment
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(EnrichmentInterface $enrichment): bool
    {
        try {
            $this->resource->delete($enrichment);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete enrichment: %1', $e->getMessage()), $e);
        }

        return true;
    }
}
