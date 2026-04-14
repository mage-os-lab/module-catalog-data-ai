<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class AttributeColumn extends Select
{
    private array $attributeOptions = [];

    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        ?Context $context = null,
        array $data = []
    ) {
        if ($context !== null) {
            parent::__construct($context, $data);
        }
    }

    public function getOptions(): array
    {
        if (empty($this->attributeOptions)) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('frontend_input', ['text', 'textarea'], 'in')
                ->create();

            $attributes = $this->attributeRepository->getList($searchCriteria);

            foreach ($attributes->getItems() as $attribute) {
                $this->attributeOptions[$attribute->getAttributeCode()] = $attribute->getDefaultFrontendLabel()
                    ?? $attribute->getAttributeCode();
            }

            asort($this->attributeOptions);
        }

        return $this->attributeOptions;
    }

    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getOptions());
        }

        foreach ($this->getOptions() as $code => $label) {
            $this->addOption($code, $label);
        }

        return parent::_toHtml();
    }
}
