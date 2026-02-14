<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Trait;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Provides mass action filter and collection mock setup for enrichment controller tests.
 */
trait MassActionMockTrait
{
    protected Filter&MockObject $filter;
    protected CollectionFactory&MockObject $collectionFactory;

    /**
     * Create Filter and CollectionFactory mocks (call in setUp).
     */
    protected function setUpMassActionMocks(): void
    {
        $this->filter = $this->createMock(Filter::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
    }

    /**
     * Configure the filter to return a collection iterating over the given items.
     *
     * @param array $items
     */
    protected function setUpFilteredCollection(array $items): void
    {
        $collection = $this->createMock(AbstractDb::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));

        $this->filter
            ->method('getCollection')
            ->willReturn($collection);

        $this->collectionFactory
            ->method('create')
            ->willReturn($collection);
    }
}
