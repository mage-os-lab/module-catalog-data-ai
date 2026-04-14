<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;

class EnrichmentStatus extends AbstractModifier
{
    public function __construct(
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly Config $config,
        private readonly ArrayManager $arrayManager
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $enrichableAttributes = array_keys($this->config->getEnrichableAttributes());

        foreach ($enrichableAttributes as $attributeCode) {
            $containerPath = $this->arrayManager->findPath(
                $attributeCode,
                $meta,
                null,
                'children'
            );

            if ($containerPath) {
                $meta = $this->arrayManager->merge(
                    $containerPath . '/arguments/data/config',
                    $meta,
                    [
                        'additionalClasses' => 'mageos-catalogai-enriched-field',
                    ]
                );
            }
        }

        return $meta;
    }
}
