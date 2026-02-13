<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Catalog\Model\Product;

class Config
{
    public const XML_PATH_ENRICH_ENABLED = 'catalog_ai/settings/active';
    public const XML_PATH_USE_ASYNC = 'catalog_ai/settings/async';
    public const XML_PATH_OPENAI_ORGANIZATION_ID = 'catalog_ai/settings/openai_organization_id';
    public const XML_PATH_OPENAI_API_KEY = 'catalog_ai/settings/openai_key';
    public const XML_PATH_OPENAI_PROJECT_ID = 'catalog_ai/settings/openai_project_id';
    public const XML_PATH_OPENAI_API_MODEL = 'catalog_ai/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'catalog_ai/settings/openai_max_tokens';
    public const XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT = 'catalog_ai/advanced/system_prompt';
    public const XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE = 'catalog_ai/advanced/temperature';
    public const XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY = 'catalog_ai/advanced/frequency_penalty';
    public const XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY = 'catalog_ai/advanced/presence_penalty';
    public const XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS = 'catalog_ai/product/attribute_prompts';

    private ?array $attributePromptsMap = null;

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

    public function getProductPrompt(string $attributeCode): ?string
    {
        return $this->getAttributePromptsMap()[$attributeCode] ?? null;
    }

    /**
     * @return string[] Attribute codes that have non-empty prompts configured.
     */
    public function getConfiguredAttributes(): array
    {
        return array_keys($this->getAttributePromptsMap());
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

    /**
     * Parse the serialized attribute_prompts config into [attribute_code => prompt].
     *
     * Handles both the array format from config.xml defaults and the JSON string
     * stored in the database by the ArraySerialized backend model.
     *
     * @return array<string, string>
     */
    private function getAttributePromptsMap(): array
    {
        if ($this->attributePromptsMap !== null) {
            return $this->attributePromptsMap;
        }

        $this->attributePromptsMap = [];

        $value = $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_ATTRIBUTE_PROMPTS);

        if (is_string($value)) {
            try {
                $value = $this->json->unserialize($value);
            } catch (\InvalidArgumentException $e) {
                return $this->attributePromptsMap;
            }
        }

        if (!is_array($value)) {
            return $this->attributePromptsMap;
        }

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = $row['attribute_code'] ?? '';
            $prompt = $row['prompt'] ?? '';
            if ($code !== '' && $prompt !== '') {
                $this->attributePromptsMap[$code] = $prompt;
            }
        }

        return $this->attributePromptsMap;
    }
}
