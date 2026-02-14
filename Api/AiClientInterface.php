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
}
