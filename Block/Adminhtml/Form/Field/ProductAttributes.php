<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class ProductAttributes extends AbstractFieldArray
{
    private ?AttributeColumn $attributeRenderer = null;

    protected function _prepareToRender(): void
    {
        $this->addColumn('attribute', [
            'label' => __('Attribute'),
            'renderer' => $this->getAttributeRenderer(),
            'class' => 'required-entry',
        ]);
        $this->addColumn('prompt', [
            'label' => __('Prompt'),
            'class' => 'required-entry',
        ]);
        $this->addColumn('enabled', [
            'label' => __('Enabled'),
            'class' => 'required-entry',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $attribute = $row->getData('attribute');

        if ($attribute !== null) {
            $key = 'option_' . $this->getAttributeRenderer()->calcOptionHash($attribute);
            $options[$key] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @throws LocalizedException
     */
    private function getAttributeRenderer(): AttributeColumn
    {
        if ($this->attributeRenderer === null) {
            $this->attributeRenderer = $this->getLayout()->createBlock(
                AttributeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->attributeRenderer;
    }
}
