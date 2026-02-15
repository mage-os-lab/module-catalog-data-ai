<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\Product;

class Config
{
    public const XML_PATH_ENRICH_ENABLED = 'catalog_ai/settings/active';
    public const XML_PATH_USE_ASYNC = 'catalog_ai/settings/async';
    public const XML_PATH_ENRICHMENT_CACHE = 'catalog_ai/settings/enrichment_cache';
    public const XML_PATH_APPROVAL_WORKFLOW = 'catalog_ai/settings/approval_workflow';
    public const XML_PATH_OPENAI_ORGANIZATION_ID = 'catalog_ai/settings/openai_organization_id';
    public const XML_PATH_OPENAI_API_KEY = 'catalog_ai/settings/openai_key';
    public const XML_PATH_OPENAI_PROJECT_ID = 'catalog_ai/settings/openai_project_id';
    public const XML_PATH_OPENAI_API_MODEL = 'catalog_ai/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'catalog_ai/settings/openai_max_tokens';
    public const XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT = 'catalog_ai/advanced/system_prompt';
    public const XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE = 'catalog_ai/advanced/temperature';
    public const XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY = 'catalog_ai/advanced/frequency_penalty';
    public const XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY = 'catalog_ai/advanced/presence_penalty';
    public const XML_PATH_CONTEXT_VALUE_MAX_LENGTH = 'catalog_ai/advanced/context_value_max_length';
    public const XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS = 'catalog_ai/product/attribute_prompts';

    private array $attributePromptsMap = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {}

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

    public function isCacheEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENRICHMENT_CACHE);
    }

    public function isApprovalRequired(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_APPROVAL_WORKFLOW);
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

    public function getProductPrompt(string $attributeCode, ?int $storeId = null): ?string
    {
        return $this->getAttributePromptsMap($storeId)[$attributeCode] ?? null;
    }

    /**
     * @return string[] Attribute codes that have non-empty prompts configured.
     */
    public function getConfiguredAttributes(?int $storeId = null): array
    {
        return array_keys($this->getAttributePromptsMap($storeId));
    }

    public function canEnrich(Product $product): bool
    {
        return $this->isEnabled() && $this->getApiKey() && $product->isObjectNew();
    }

    public function getSystemPrompt(): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT
        );
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

    public function getContextValueMaxLength(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_CONTEXT_VALUE_MAX_LENGTH);
    }

    /**
     * Parse the serialized attribute_prompts config into [attribute_code => prompt].
     *
     * Handles both the array format from config.xml defaults and the JSON string
     * stored in the database by the ArraySerialized backend model.
     *
     * @return array<string, string>
     */
    private function getAttributePromptsMap(?int $storeId = null): array
    {
        $cacheKey = $storeId ?? 'default';

        if (isset($this->attributePromptsMap[$cacheKey])) {
            return $this->attributePromptsMap[$cacheKey];
        }

        $this->attributePromptsMap[$cacheKey] = [];

        $value = $storeId !== null
            ? $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS, ScopeInterface::SCOPE_STORES, $storeId)
            : $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS);

        if (is_string($value)) {
            try {
                $value = $this->json->unserialize($value);
            } catch (\InvalidArgumentException $e) {
                return $this->attributePromptsMap[$cacheKey];
            }
        }

        if (!is_array($value)) {
            return $this->attributePromptsMap[$cacheKey];
        }

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = $row['attribute_code'] ?? '';
            $prompt = $row['prompt'] ?? '';
            if ($code !== '' && $prompt !== '') {
                $this->attributePromptsMap[$cacheKey][$code] = $prompt;
            }
        }

        return $this->attributePromptsMap[$cacheKey];
    }
}
