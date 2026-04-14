<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(EnrichmentLog::class, EnrichmentLogResource::class);
    }
}
