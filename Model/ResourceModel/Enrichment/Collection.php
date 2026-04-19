<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\Enrichment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\Enrichment;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment as EnrichmentResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Enrichment::class, EnrichmentResource::class);
    }
}
