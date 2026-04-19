<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterfaceFactory;
use MageOS\CatalogDataAI\Model\Enrichment;
use MageOS\CatalogDataAI\Model\EnrichmentFactory;
use MageOS\CatalogDataAI\Model\EnrichmentRepository;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment as EnrichmentResource;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\Collection;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnrichmentRepositoryTest extends TestCase
{
    private EnrichmentFactory&MockObject $enrichmentFactory;
    private EnrichmentResource&MockObject $resource;
    private CollectionFactory&MockObject $collectionFactory;
    private EnrichmentSearchResultsInterfaceFactory&MockObject $searchResultsFactory;
    private CollectionProcessorInterface&MockObject $collectionProcessor;
    private EnrichmentRepository $repository;

    protected function setUp(): void
    {
        $this->enrichmentFactory = $this->createMock(EnrichmentFactory::class);
        $this->resource = $this->createMock(EnrichmentResource::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->searchResultsFactory = $this->createMock(EnrichmentSearchResultsInterfaceFactory::class);
        $this->collectionProcessor = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new EnrichmentRepository(
            $this->enrichmentFactory,
            $this->resource,
            $this->collectionFactory,
            $this->searchResultsFactory,
            $this->collectionProcessor
        );
    }

    /**
     * @return void
     */
    public function testSaveSuccess(): void
    {
        $enrichment = $this->createMock(Enrichment::class);

        $this->resource->expects($this->once())
            ->method('save')
            ->with($enrichment);

        $result = $this->repository->save($enrichment);

        $this->assertSame($enrichment, $result);
    }

    /**
     * @return void
     */
    public function testSaveDuplicateExceptionWithExistingFound(): void
    {
        $enrichment = $this->createMock(Enrichment::class);
        $existingEnrichment = $this->createMock(Enrichment::class);
        $collection = $this->createMock(Collection::class);

        $enrichment->expects($this->once())
            ->method('getPromptHash')
            ->willReturn('hash123');
        $enrichment->expects($this->once())
            ->method('getAttributeCode')
            ->willReturn('description');
        $enrichment->expects($this->once())
            ->method('getStoreId')
            ->willReturn(1);

        $this->resource->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willThrowException(new DuplicateException(__('Duplicate entry')));

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->exactly(3))
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $value) use ($collection) {
                return $collection;
            });

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();

        $existingEnrichment->expects($this->once())
            ->method('getEntityId')
            ->willReturn(42);

        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($existingEnrichment);

        $result = $this->repository->save($enrichment);

        $this->assertSame($existingEnrichment, $result);
    }

    /**
     * @return void
     */
    public function testSaveDuplicateExceptionWithNoExisting(): void
    {
        $enrichment = $this->createMock(Enrichment::class);
        $emptyItem = $this->createMock(Enrichment::class);
        $collection = $this->createMock(Collection::class);

        $enrichment->expects($this->once())
            ->method('getPromptHash')
            ->willReturn('hash123');
        $enrichment->expects($this->once())
            ->method('getAttributeCode')
            ->willReturn('description');
        $enrichment->expects($this->once())
            ->method('getStoreId')
            ->willReturn(1);

        $this->resource->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willThrowException(new DuplicateException(__('Duplicate entry')));

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->exactly(3))
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $value) use ($collection) {
                return $collection;
            });

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();

        $emptyItem->expects($this->once())
            ->method('getEntityId')
            ->willReturn(null);

        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($emptyItem);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save enrichment:');

        $this->repository->save($enrichment);
    }

    /**
     * @return void
     */
    public function testSaveGenericException(): void
    {
        $enrichment = $this->createMock(Enrichment::class);

        $this->resource->expects($this->once())
            ->method('save')
            ->with($enrichment)
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save enrichment:');

        $this->repository->save($enrichment);
    }

    /**
     * @return void
     */
    public function testGetByIdFound(): void
    {
        $enrichment = $this->createMock(Enrichment::class);

        $this->enrichmentFactory->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->resource->expects($this->once())
            ->method('load')
            ->with($enrichment, 42);

        $enrichment->expects($this->once())
            ->method('getEntityId')
            ->willReturn(42);

        $result = $this->repository->getById(42);

        $this->assertSame($enrichment, $result);
    }

    /**
     * @return void
     */
    public function testGetByIdNotFound(): void
    {
        $enrichment = $this->createMock(Enrichment::class);

        $this->enrichmentFactory->expects($this->once())
            ->method('create')
            ->willReturn($enrichment);

        $this->resource->expects($this->once())
            ->method('load')
            ->with($enrichment, 42);

        $enrichment->expects($this->once())
            ->method('getEntityId')
            ->willReturn(null);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Enrichment with ID "42" does not exist.');

        $this->repository->getById(42);
    }

    /**
     * @return void
     */
    public function testGetByHashFound(): void
    {
        $collection = $this->createMock(Collection::class);
        $item = $this->createMock(Enrichment::class);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->exactly(3))
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $value) use ($collection) {
                return $collection;
            });

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();

        $item->expects($this->once())
            ->method('getEntityId')
            ->willReturn(42);

        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($item);

        $result = $this->repository->getByHash('hash123', 'description', 1);

        $this->assertSame($item, $result);
    }

    /**
     * @return void
     */
    public function testGetByHashNotFound(): void
    {
        $collection = $this->createMock(Collection::class);
        $emptyItem = $this->createMock(Enrichment::class);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->exactly(3))
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $value) use ($collection) {
                return $collection;
            });

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();

        $emptyItem->expects($this->once())
            ->method('getEntityId')
            ->willReturn(null);

        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($emptyItem);

        $result = $this->repository->getByHash('hash123', 'description', 1);

        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testGetByHashFiltersCorrectly(): void
    {
        $collection = $this->createMock(Collection::class);
        $emptyItem = $this->createMock(Enrichment::class);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->exactly(3))
            ->method('addFieldToFilter')
            ->willReturnCallback(function ($field, $value) use ($collection) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    $this->assertEquals('prompt_hash', $field);
                    $this->assertEquals('hash123', $value);
                } elseif ($callCount === 2) {
                    $this->assertEquals('attribute_code', $field);
                    $this->assertEquals('description', $value);
                } elseif ($callCount === 3) {
                    $this->assertEquals('store_id', $field);
                    $this->assertEquals(1, $value);
                }

                return $collection;
            });

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();

        $emptyItem->expects($this->once())
            ->method('getEntityId')
            ->willReturn(null);

        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($emptyItem);

        $this->repository->getByHash('hash123', 'description', 1);
    }

    /**
     * @return void
     */
    public function testGetListDelegatesCorrectly(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $collection = $this->createMock(Collection::class);
        $searchResults = $this->createMock(EnrichmentSearchResultsInterface::class);
        $items = [$this->createMock(Enrichment::class)];

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $this->collectionProcessor->expects($this->once())
            ->method('process')
            ->with($criteria, $collection);

        $collection->expects($this->once())
            ->method('getItems')
            ->willReturn($items);

        $collection->expects($this->once())
            ->method('getSize')
            ->willReturn(1);

        $this->searchResultsFactory->expects($this->once())
            ->method('create')
            ->willReturn($searchResults);

        $searchResults->expects($this->once())
            ->method('setSearchCriteria')
            ->with($criteria)
            ->willReturnSelf();

        $searchResults->expects($this->once())
            ->method('setItems')
            ->with($items)
            ->willReturnSelf();

        $searchResults->expects($this->once())
            ->method('setTotalCount')
            ->with(1)
            ->willReturnSelf();

        $result = $this->repository->getList($criteria);

        $this->assertSame($searchResults, $result);
    }

    /**
     * @return void
     */
    public function testDeleteSuccess(): void
    {
        $enrichment = $this->createMock(Enrichment::class);

        $this->resource->expects($this->once())
            ->method('delete')
            ->with($enrichment);

        $result = $this->repository->delete($enrichment);

        $this->assertTrue($result);
    }

    /**
     * @return void
     */
    public function testDeleteException(): void
    {
        $enrichment = $this->createMock(Enrichment::class);

        $this->resource->expects($this->once())
            ->method('delete')
            ->with($enrichment)
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete enrichment:');

        $this->repository->delete($enrichment);
    }
}
