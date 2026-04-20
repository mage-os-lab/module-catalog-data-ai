<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ExtractionRule extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageos_catalogai_extraction_rule', 'rule_id');
    }
}
