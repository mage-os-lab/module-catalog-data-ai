<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\PromptRule;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;

class PromptResolver
{
    public function __construct(
        private readonly CollectionFactory $ruleCollectionFactory,
        private readonly Config $config
    ) {
    }

    public function resolve(string $attributeCode, Product $product): ?string
    {
        $storeId = (int)$product->getStoreId();

        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('attribute_code', $attributeCode);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'DESC');

        /** @var PromptRule $rule */
        foreach ($collection as $rule) {
            if ($rule->matchesStore($storeId) && $rule->matchesProduct($product)) {
                return $rule->getPrompt();
            }
        }

        return $this->config->getProductPrompt($attributeCode);
    }
}
