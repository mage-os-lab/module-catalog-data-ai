<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateProductPromptsToDynamicRows implements DataPatchInterface
{
    private const OLD_ATTRIBUTE_PATHS = [
        'short_description',
        'description',
        'meta_title',
        'meta_keyword',
        'meta_description',
    ];

    private const NEW_PATH = 'catalog_ai/product/attribute_prompts';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly Json $json
    ) {
    }

    public function apply(): self
    {
        $rows = [];
        $hasExistingConfig = false;

        foreach (self::OLD_ATTRIBUTE_PATHS as $attributeCode) {
            $oldPath = 'catalog_ai/product/' . $attributeCode;
            $value = $this->scopeConfig->getValue($oldPath);

            if ($value !== null && $value !== '') {
                $hasExistingConfig = true;
                $rows[$attributeCode] = [
                    'attribute' => $attributeCode,
                    'prompt' => $value,
                    'enabled' => '1',
                ];
            }
        }

        if ($hasExistingConfig) {
            $this->configWriter->save(self::NEW_PATH, $this->json->serialize($rows));

            // Clean up old paths
            foreach (self::OLD_ATTRIBUTE_PATHS as $attributeCode) {
                $this->configWriter->delete('catalog_ai/product/' . $attributeCode);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
