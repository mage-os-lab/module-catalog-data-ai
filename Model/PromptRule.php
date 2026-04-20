<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\Rule\Model\AbstractModel;
use MageOS\CatalogDataAI\Api\Data\PromptRuleInterface;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class PromptRule extends AbstractModel implements PromptRuleInterface
{
    protected $_eventPrefix = 'mageos_catalogai_prompt_rule';

    protected function _construct(): void
    {
        $this->_init(PromptRuleResource::class);
    }

    public function getConditionsInstance(): \Magento\Rule\Model\Condition\Combine
    {
        return $this->_conditionFactory->create(Combine::class);
    }

    public function getActionsInstance(): \Magento\Rule\Model\Action\Collection
    {
        return $this->_actionFactory->create(\Magento\Rule\Model\Action\Collection::class);
    }

    public function getRuleId(): ?int
    {
        return $this->getData(self::RULE_ID) ? (int)$this->getData(self::RULE_ID) : null;
    }

    public function getName(): string
    {
        return (string)$this->getData(self::NAME);
    }

    public function getAttributeCode(): string
    {
        return (string)$this->getData(self::ATTRIBUTE_CODE);
    }

    public function getStoreIds(): string
    {
        return (string)$this->getData(self::STORE_IDS);
    }

    public function getConditionsSerialized(): ?string
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    public function getPrompt(): string
    {
        return (string)$this->getData(self::PROMPT);
    }

    public function getPriority(): int
    {
        return (int)$this->getData(self::PRIORITY);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function matchesProduct(\Magento\Catalog\Model\Product $product): bool
    {
        return $this->getConditions()->validate($product);
    }

    public function matchesStore(int $storeId): bool
    {
        $storeIds = $this->getStoreIds();
        if ($storeIds === '' || $storeIds === '0') {
            return true;
        }
        $ids = array_map('intval', explode(',', $storeIds));
        return in_array(0, $ids, true) || in_array($storeId, $ids, true);
    }
}
