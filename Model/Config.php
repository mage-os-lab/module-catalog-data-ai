<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\Product;

class Config
{
    public const XML_PATH_ENRICH_ENABLED = 'catalog_ai/settings/active';
    public const XML_PATH_USE_ASYNC = 'catalog_ai/settings/async';
    public const XML_PATH_DEBUG_LOG = 'catalog_ai/settings/debug_log';
    public const XML_PATH_OPENAI_ORGANIZATION_ID = 'catalog_ai/settings/openai_organization_id';
    public const XML_PATH_OPENAI_API_KEY = 'catalog_ai/settings/openai_key';
    public const XML_PATH_OPENAI_PROJECT_ID = 'catalog_ai/settings/openai_project_id';
    public const XML_PATH_OPENAI_API_MODEL = 'catalog_ai/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'catalog_ai/settings/openai_max_tokens';
    public const XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT = 'catalog_ai/advanced/system_prompt';
    public const XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE = 'catalog_ai/advanced/temperature';
    public const XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY = 'catalog_ai/advanced/frequency_penalty';
    public const XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY = 'catalog_ai/advanced/presence_penalty';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
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

    public function isDebugLogEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_LOG
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

    public function getProductPrompt(string $attributeCode): mixed
    {
        $path = 'catalog_ai/product/' . $attributeCode;
        return $this->scopeConfig->getValue(
            $path
        );
    }
    public function getProductPromptToken(string $attributeCode): mixed
    {
        $path = 'catalog_ai/product/' . $attributeCode;
        return $this->scopeConfig->getValue(
            $path
        );
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
}
