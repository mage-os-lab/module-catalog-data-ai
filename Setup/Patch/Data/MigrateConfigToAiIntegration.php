<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateConfigToAiIntegration implements DataPatchInterface
{
    private const PATH_MAP = [
        'catalog_ai/settings/active' => 'ai_integration_enrichment/settings/active',
        'catalog_ai/settings/async' => 'ai_integration_enrichment/settings/async',
        'catalog_ai/settings/openai_organization_id' => 'ai_integration_enrichment/settings/openai_organization_id',
        'catalog_ai/settings/openai_key' => 'ai_integration_enrichment/settings/openai_key',
        'catalog_ai/settings/openai_project_id' => 'ai_integration_enrichment/settings/openai_project_id',
        'catalog_ai/settings/openai_model' => 'ai_integration_enrichment/settings/openai_model',
        'catalog_ai/settings/openai_max_tokens' => 'ai_integration_enrichment/settings/openai_max_tokens',
        'catalog_ai/product/attribute_prompts' => 'ai_integration_enrichment/product/attribute_prompts',
        'catalog_ai/advanced/system_prompt' => 'ai_integration_enrichment/advanced/system_prompt',
        'catalog_ai/advanced/temperature' => 'ai_integration_enrichment/advanced/temperature',
        'catalog_ai/advanced/frequency_penalty' => 'ai_integration_enrichment/advanced/frequency_penalty',
        'catalog_ai/advanced/presence_penalty' => 'ai_integration_enrichment/advanced/presence_penalty',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        foreach (self::PATH_MAP as $oldPath => $newPath) {
            $value = $this->scopeConfig->getValue($oldPath);
            if ($value !== null) {
                $this->configWriter->save($newPath, $value);
                $this->configWriter->delete($oldPath);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [MigrateProductPromptsToDynamicRows::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
