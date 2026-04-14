<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\EnrichmentLogFactory;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\Collection;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;
use PHPUnit\Framework\TestCase;

final class EnrichmentLoggerTest extends TestCase
{
    public function test_log_creates_new_entry_with_content_and_hash(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($this->createConfiguredMock(
            EnrichmentLog::class,
            ['getId' => null]
        ));

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $newLog = $this->createMock(EnrichmentLog::class);
        $newLog->expects($this->once())->method('setData')->with($this->callback(
            fn(array $data) => $data['entity_id'] === 42
                && $data['attribute_code'] === 'description'
                && $data['store_id'] === 1
                && $data['status'] === EnrichmentLog::STATUS_GENERATED
                && $data['generated_content'] === 'AI output'
                && $data['original_content'] === 'old value'
                && $data['prompt_hash'] === 'abc123'
        ));

        $logFactory = $this->createMock(EnrichmentLogFactory::class);
        $logFactory->method('create')->willReturn($newLog);

        $resource = $this->createMock(EnrichmentLogResource::class);
        $resource->expects($this->once())->method('save')->with($newLog);

        $logger = new EnrichmentLogger($collectionFactory, $logFactory, $resource);
        $logger->log(42, 'description', 1, 'AI output', 'old value', 'abc123');
    }

    public function test_find_by_prompt_hash_returns_cached_content(): void
    {
        $item = $this->createMock(EnrichmentLog::class);
        $item->method('getId')->willReturn(1);
        $item->method('getData')->with('generated_content')->willReturn('cached response');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($item);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $logFactory = $this->createMock(EnrichmentLogFactory::class);
        $resource = $this->createMock(EnrichmentLogResource::class);

        $logger = new EnrichmentLogger($collectionFactory, $logFactory, $resource);
        $result = $logger->findByPromptHash('hash123', 'description', 1);

        $this->assertEquals('cached response', $result);
    }
}
