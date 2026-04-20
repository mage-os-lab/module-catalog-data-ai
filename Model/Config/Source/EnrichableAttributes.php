<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Config\Source;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

class EnrichableAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('frontend_input', ['text', 'textarea'], 'in')
            ->create();

        $attributes = $this->attributeRepository->getList($searchCriteria);

        $options = [['value' => '', 'label' => __('-- Please Select --')]];
        foreach ($attributes->getItems() as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => ($attribute->getDefaultFrontendLabel() ?? $attribute->getAttributeCode()),
            ];
        }

        usort($options, fn ($a, $b) => strcmp((string)$a['label'], (string)$b['label']));

        return $options;
    }
}
