<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class EnrichmentLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageos_catalogai_enrichment_log', 'log_id');
    }
}
