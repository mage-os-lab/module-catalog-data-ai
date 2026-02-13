<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class AttributeColumn extends Select
{
    public function __construct(
        Context $context,
        private readonly CollectionFactory $attributeCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setInputName($value): self
    {
        return $this->setName($value);
    }

    public function setInputId($value): self
    {
        return $this->setId($value);
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->addOption('', (string)__('--Please Select--'));

            $collection = $this->attributeCollectionFactory->create();
            $collection->addFieldToFilter('frontend_input', ['in' => ['text', 'textarea', 'texteditor']]);
            $collection->setOrder('frontend_label', 'ASC');

            foreach ($collection as $attribute) {
                $code = $attribute->getAttributeCode();
                $label = $attribute->getFrontendLabel();
                if ($label) {
                    $this->addOption($code, sprintf('%s (%s)', $label, $code));
                }
            }
        }

        return parent::_toHtml();
    }
}
