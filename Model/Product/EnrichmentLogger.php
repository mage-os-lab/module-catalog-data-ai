<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\EnrichmentLogFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;

class EnrichmentLogger
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentLogFactory $logFactory,
        private readonly EnrichmentLogResource $resource
    ) {
    }

    public function log(
        int $entityId,
        string $attributeCode,
        int $storeId,
        string $generatedContent = '',
        string $originalContent = '',
        string $promptHash = '',
        string $status = EnrichmentLog::STATUS_GENERATED
    ): void {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        if ($existing->getId()) {
            $existing->setData('status', $status);
            $existing->setData('generated_content', $generatedContent);
            $existing->setData('original_content', $originalContent);
            $existing->setData('prompt_hash', $promptHash);
            $this->resource->save($existing);
        } else {
            $log = $this->logFactory->create();
            $log->setData([
                'entity_id' => $entityId,
                'attribute_code' => $attributeCode,
                'store_id' => $storeId,
                'status' => $status,
                'generated_content' => $generatedContent,
                'original_content' => $originalContent,
                'prompt_hash' => $promptHash,
            ]);
            $this->resource->save($log);
        }
    }

    public function markModified(int $entityId, string $attributeCode, int $storeId): void
    {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        if ($existing->getId() && $existing->getData('status') !== EnrichmentLog::STATUS_MODIFIED) {
            $existing->setData('status', EnrichmentLog::STATUS_MODIFIED);
            $this->resource->save($existing);
        }
    }

    public function approve(int $logId): ?EnrichmentLog
    {
        $log = $this->logFactory->create();
        $this->resource->load($log, $logId);

        if ($log->getId()) {
            $log->setData('status', EnrichmentLog::STATUS_APPROVED);
            $this->resource->save($log);
            return $log;
        }

        return null;
    }

    public function reject(int $logId): ?EnrichmentLog
    {
        $log = $this->logFactory->create();
        $this->resource->load($log, $logId);

        if ($log->getId()) {
            $log->setData('status', EnrichmentLog::STATUS_REJECTED);
            $this->resource->save($log);
            return $log;
        }

        return null;
    }

    public function findByPromptHash(string $promptHash, string $attributeCode, int $storeId): ?string
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('prompt_hash', $promptHash);
        $collection->addFieldToFilter('attribute_code', $attributeCode);
        $collection->addFieldToFilter('store_id', $storeId);
        $collection->addFieldToFilter('generated_content', ['notnull' => true]);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item->getData('generated_content') : null;
    }

    public function getStatus(int $entityId, string $attributeCode, int $storeId): ?string
    {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        return $existing->getId() ? $existing->getData('status') : null;
    }

    public function getStatusesForProduct(int $entityId, int $storeId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', $entityId);
        $collection->addFieldToFilter('store_id', $storeId);

        $statuses = [];
        foreach ($collection as $item) {
            $statuses[$item->getData('attribute_code')] = $item->getData('status');
        }

        return $statuses;
    }

    private function find(int $entityId, string $attributeCode, int $storeId): EnrichmentLog
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', $entityId);
        $collection->addFieldToFilter('attribute_code', $attributeCode);
        $collection->addFieldToFilter('store_id', $storeId);

        return $collection->getFirstItem();
    }
}
