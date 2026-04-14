<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PromptRule extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageos_catalogai_prompt_rule', 'rule_id');
    }
}
