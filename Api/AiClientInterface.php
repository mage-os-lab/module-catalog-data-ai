<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api;

interface AiClientInterface
{
    /**
     * Send a chat completion request and return the generated text.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return string|null
     */
    public function generate(string $systemPrompt, string $userPrompt): ?string;

    /**
     * Generate content for multiple attributes in a single API call.
     *
     * Returns an empty array on failure (JSON parse error, missing keys, etc.)
     * to signal the caller should fall back to individual generate() calls.
     *
     * @param string $systemPrompt
     * @param string $productContext  Formatted product attribute data
     * @param array<string, string> $attributePrompts  [attribute_code => parsed_prompt]
     * @return array<string, string>  [attribute_code => generated_value]
     */
    public function generateBatch(
        string $systemPrompt,
        string $productContext,
        array $attributePrompts
    ): array;
}
