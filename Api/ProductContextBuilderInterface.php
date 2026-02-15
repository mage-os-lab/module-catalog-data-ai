<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api;

use Magento\Catalog\Model\Product;

interface ProductContextBuilderInterface
{
    /**
     * Build a text representation of product attributes for AI context.
     *
     * @param Product $product
     * @return string
     */
    public function build(Product $product): string;
}
