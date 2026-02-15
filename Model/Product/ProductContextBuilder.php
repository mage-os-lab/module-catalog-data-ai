<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Api\ProductContextBuilderInterface;
use MageOS\CatalogDataAI\Model\Config;

class ProductContextBuilder implements ProductContextBuilderInterface
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function build(Product $product): string
    {
        $maxLength = $this->config->getContextValueMaxLength();
        $lines = [];

        foreach ($product->getAttributes() as $attribute) {
            if (!$attribute->getIsVisibleOnFront() && !$attribute->getIsSearchable()) {
                continue;
            }

            $value = $product->getDataUsingMethod($attribute->getAttributeCode());
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $value = (string) $value;
            if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
                $value = mb_substr($value, 0, $maxLength) . '...';
            }

            $lines[] = ($attribute->getStoreLabel() ?: $attribute->getAttributeCode()) . ': ' . $value;
        }

        return implode("\n", $lines);
    }
}
