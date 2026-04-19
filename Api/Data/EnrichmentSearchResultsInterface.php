<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface EnrichmentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MageOS\CatalogDataAI\Api\Data\EnrichmentInterface[]
     */
    public function getItems();

    /**
     * @param \MageOS\CatalogDataAI\Api\Data\EnrichmentInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
