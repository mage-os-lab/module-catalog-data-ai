<?php

/**
 * Copyright © 2026 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Framework\Api\SearchResults;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterface;

class EnrichmentSearchResults extends SearchResults implements EnrichmentSearchResultsInterface
{
}
