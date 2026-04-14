<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\Rule\Model\AbstractModel;
use MageOS\CatalogDataAI\Api\Data\ExtractionRuleInterface;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class ExtractionRule extends AbstractModel implements ExtractionRuleInterface
{
    protected $_eventPrefix = 'mageos_catalogai_extraction_rule';

    protected function _construct(): void
    {
        $this->_init(ExtractionRuleResource::class);
    }

    public function getConditionsInstance(): \Magento\Rule\Model\Condition\Combine
    {
        return $this->_conditionFactory->create(Combine::class);
    }

    public function getActionsInstance(): \Magento\Rule\Model\Action\Collection
    {
        return $this->_actionFactory->create(\Magento\Rule\Model\Action\Collection::class);
    }

    public function getSourceAttributes(): array
    {
        $value = (string)$this->getData(self::SOURCE_ATTRIBUTES);
        return $value ? array_map('trim', explode(',', $value)) : [];
    }

    public function getTargetAttribute(): string
    {
        return (string)$this->getData(self::TARGET_ATTRIBUTE);
    }

    public function getExtractionPrompt(): string
    {
        return (string)$this->getData(self::EXTRACTION_PROMPT);
    }

    public function getValueMapping(): array
    {
        $json = $this->getData(self::VALUE_MAPPING);
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function matchesProduct(\Magento\Catalog\Model\Product $product): bool
    {
        return $this->getConditions()->validate($product);
    }

    public function matchesStore(int $storeId): bool
    {
        $storeIds = (string)$this->getData(self::STORE_IDS);
        if ($storeIds === '' || $storeIds === '0') {
            return true;
        }
        $ids = array_map('intval', explode(',', $storeIds));
        return in_array(0, $ids, true) || in_array($storeId, $ids, true);
    }
}
