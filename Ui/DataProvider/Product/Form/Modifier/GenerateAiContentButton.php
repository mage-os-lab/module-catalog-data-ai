<?php

/**
 * Copyright © Elgentos. All rights reserved.
 * https://elgentos.nl
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\CatalogDataAI\Model\Product\Enricher;

class GenerateAiContentButton extends AbstractModifier
{
    public const TITLE = 'Generate Ai Content';
    public const URL_CONTROLLER = 'catalog_data_ai/catalog/aicontent';
    public const PATH_SUFFIX_AI_BUTTON = '/ai_button/arguments/data/config';

    public function __construct(
        protected Enricher $enricher,
        protected StoreManagerInterface $storeManager,
        protected RequestInterface $request,
        protected ArrayManager $arrayManager,
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        foreach ($this->getAttributes() as $attributeCode) {
            $path = $this->getParentPath($attributeCode, $meta);
            if ($path === null) {
                continue;
            }

            $pathSuffix = self::PATH_SUFFIX_AI_BUTTON;
            $meta = $this->arrayManager->populate($path . $pathSuffix, $meta);

            $button = $this->generateAiContentButton($attributeCode);

            $meta = $this->arrayManager->set($path . $pathSuffix, $meta, $button);
        }

        return $meta;
    }

    public function getParentPath(string $attributeCode, array $meta): ?string
    {
        $origPath = $this->arrayManager->findPath($attributeCode, $meta);
        if (!$origPath) {
            return null;
        }

        $pathArray = explode('/', $origPath);
        array_pop($pathArray);

        return implode('/', $pathArray);
    }

    public function getAttributes(): array
    {
        return $this->enricher->getAttributes();
    }

    public function generateAiContentButton(string $attributeCode): array
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $title = self::TITLE;
        return [
            'url' => $baseUrl . self::URL_CONTROLLER,
            'product_id' => $this->request->getParam('id'),
            'targetName' => $attributeCode,
            'title' => __($title),
            'componentType' => "button",
            'component' => 'MageOS_CatalogDataAI/js/components/generate-ai-component',
            'template' => 'ui/form/components/button/container',
            'displayAsLink' => false,
            'additionalForGroup' => true,
            'provider' => false,
            'source' => self::DEFAULT_GENERAL_PANEL,
            'additionalClasses' => 'admin__field-small'
        ];
    }
}
