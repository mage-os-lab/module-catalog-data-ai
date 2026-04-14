<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public const XML_PATH_ENRICH_ENABLED = 'ai_integration_enrichment/settings/active';
    public const XML_PATH_USE_ASYNC = 'ai_integration_enrichment/settings/async';
    public const XML_PATH_OPENAI_ORGANIZATION_ID = 'ai_integration_enrichment/settings/openai_organization_id';
    public const XML_PATH_OPENAI_API_KEY = 'ai_integration_enrichment/settings/openai_key';
    public const XML_PATH_OPENAI_PROJECT_ID = 'ai_integration_enrichment/settings/openai_project_id';
    public const XML_PATH_OPENAI_API_MODEL = 'ai_integration_enrichment/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'ai_integration_enrichment/settings/openai_max_tokens';
    public const XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT = 'ai_integration_enrichment/advanced/system_prompt';
    public const XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE = 'ai_integration_enrichment/advanced/temperature';
    public const XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY = 'ai_integration_enrichment/advanced/frequency_penalty';
    public const XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY = 'ai_integration_enrichment/advanced/presence_penalty';

    private const LOCALE_LANGUAGE_MAP = [
        'af' => 'Afrikaans', 'ar' => 'Arabic', 'bg' => 'Bulgarian', 'bn' => 'Bengali',
        'ca' => 'Catalan', 'cs' => 'Czech', 'cy' => 'Welsh', 'da' => 'Danish',
        'de' => 'German', 'el' => 'Greek', 'en' => 'English', 'es' => 'Spanish',
        'et' => 'Estonian', 'fa' => 'Persian', 'fi' => 'Finnish', 'fr' => 'French',
        'gl' => 'Galician', 'he' => 'Hebrew', 'hi' => 'Hindi', 'hr' => 'Croatian',
        'hu' => 'Hungarian', 'id' => 'Indonesian', 'it' => 'Italian', 'ja' => 'Japanese',
        'ka' => 'Georgian', 'ko' => 'Korean', 'lt' => 'Lithuanian', 'lv' => 'Latvian',
        'mk' => 'Macedonian', 'ms' => 'Malay', 'nb' => 'Norwegian', 'nl' => 'Dutch',
        'pl' => 'Polish', 'pt' => 'Portuguese', 'ro' => 'Romanian', 'ru' => 'Russian',
        'sk' => 'Slovak', 'sl' => 'Slovenian', 'sq' => 'Albanian', 'sr' => 'Serbian',
        'sv' => 'Swedish', 'th' => 'Thai', 'tr' => 'Turkish', 'uk' => 'Ukrainian',
        'vi' => 'Vietnamese', 'zh' => 'Chinese',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENRICH_ENABLED
        );
    }
    public function IsAsync(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USE_ASYNC
        );
    }

    public function getApiKey(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_KEY
        );
    }

    public function getOrganizationID(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_ORGANIZATION_ID
        );
    }

    public function getProjectId(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_PROJECT_ID
        );
    }

    public function getApiModel(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_MODEL
        );
    }
    public function getApiMaxTokens(): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_MAX_TOKENS
        );
    }

    public function getEnrichableAttributes(): array
    {
        $rows = $this->scopeConfig->getValue(
            'ai_integration_enrichment/product/attribute_prompts'
        );

        if (!is_array($rows)) {
            return [];
        }

        $attributes = [];
        foreach ($rows as $row) {
            if (isset($row['attribute'], $row['prompt'], $row['enabled']) && (int)$row['enabled'] === 1) {
                $attributes[$row['attribute']] = $row['prompt'];
            }
        }

        return $attributes;
    }

    public function getProductPrompt(string $attributeCode): ?string
    {
        $attributes = $this->getEnrichableAttributes();

        return $attributes[$attributeCode] ?? null;
    }

    public function canEnrich(Product $product): bool
    {
        return $this->isEnabled() && $this->getApiKey() && $product->isObjectNew();
    }

    public function getSystemPrompt(): string
    {
        $basePrompt = (string)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($locale) {
            $langCode = substr($locale, 0, 2);
            $language = self::LOCALE_LANGUAGE_MAP[$langCode] ?? null;
            if ($language && $langCode !== 'en') {
                $basePrompt = 'Respond in ' . $language . '. ' . $basePrompt;
            }
        }

        return $basePrompt;
    }

    public function getTemperature(): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE
        );
    }

    public function getFrequencyPenalty(): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY
        );
    }

    public function getPresencePenalty(): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY
        );
    }
}
