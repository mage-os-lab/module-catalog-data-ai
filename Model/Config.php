<?php
declare(strict_types=1);
namespace MageOS\CatalogDataAI\Model;

use Magento\Store\Model\Store;

class Config
{
    public const XML_PATH_ENRICH_ENABLED = 'catalog_ai/settings/active';
    public const XML_PATH_USE_ASYNC = 'catalog_ai/settings/async';
    public const XML_PATH_OPENAI_API_KEY = 'catalog_ai/settings/openai_key';
    public const XML_PATH_OPENAI_API_MODEL = 'catalog_ai/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'catalog_ai/settings/openai_max_tokens';

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
}
