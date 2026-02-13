<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Enrichment extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('catalogai_product_enrichment', 'entity_id');
    }
}
