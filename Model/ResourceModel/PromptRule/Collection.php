<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\PromptRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\PromptRule;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(PromptRule::class, PromptRuleResource::class);
    }
}
