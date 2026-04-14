<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\ExtractionRule;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(ExtractionRule::class, ExtractionRuleResource::class);
    }
}
