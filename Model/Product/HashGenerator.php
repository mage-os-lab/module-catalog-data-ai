<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

class HashGenerator
{
    /**
     * @param string $parsedPrompt
     * @param string $systemPrompt
     * @param string $attributeCode
     * @param int $storeId
     * @return string
     */
    public function generate(string $parsedPrompt, string $systemPrompt, string $attributeCode, int $storeId): string
    {
        return hash('sha256', implode('|', [$parsedPrompt, $systemPrompt, $attributeCode, (string) $storeId]));
    }
}
