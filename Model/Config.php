<?php
declare(strict_types=1);
namespace MageOS\CatalogDataAI\Model;

use Magento\Store\Model\Store;
USE Magento\Catalog\Model\Product;

class Config
{
    public const XML_PATH_ENRICH_ENABLED = 'catalog_ai/settings/active';
    public const XML_PATH_USE_ASYNC = 'catalog_ai/settings/async';
    public const XML_PATH_OPENAI_API_KEY = 'catalog_ai/settings/openai_key';
    public const XML_PATH_OPENAI_API_MODEL = 'catalog_ai/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'catalog_ai/settings/openai_max_tokens';
    public const XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT = 'catalog_ai/advanced/system_prompt';
    public const XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE = 'catalog_ai/advanced/temperature';
    public const XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY = 'catalog_ai/advanced/frequency_penalty';
    public const XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY = 'catalog_ai/advanced/presence_penalty';

    public function __construct(
        private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {}

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENRICH_ENABLED
        );
    }
    public function IsAsync()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USE_ASYNC
        );
    }

    public function getApiKey()
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_KEY
        );
    }
    public function getApiModel()
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_MODEL
        );
    }
    public function getApiMaxTokens()
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_MAX_TOKENS
        );
    }

    public function getProductPrompt(String $attributeCode)
    {
        $path = 'catalog_ai/product/' . $attributeCode;
        return $this->scopeConfig->getValue(
            $path
        );
    }
    public function getProductPromptToken(String $attributeCode)
    {
        $path = 'catalog_ai/product/' . $attributeCode;
        return $this->scopeConfig->getValue(
            $path
        );
    }

    public function canEnrich(Product $product)
    {
        return $this->isEnabled() && $this->getApiKey() && $product->isObjectNew();
    }

    public function getSystemPrompt()
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT
        );
    }

    public function getTemperature()
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE
        );
    }

    public function getFrequencyPenalty()
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY
        );
    }

    public function getPresencePenalty()
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY
        );
    }
}
