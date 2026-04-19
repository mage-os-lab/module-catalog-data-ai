<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class AttributePrompts extends AbstractFieldArray
{
    private ?AttributeColumn $attributeColumnRenderer = null;
    private ?PromptColumn $promptColumnRenderer = null;

    protected function _prepareToRender(): void
    {
        $this->addColumn('attribute_code', [
            'label' => __('Attribute'),
            'renderer' => $this->getAttributeColumnRenderer(),
        ]);

        $this->addColumn('prompt', [
            'label' => __('Prompt'),
            'renderer' => $this->getPromptColumnRenderer(),
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    /**
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $attributeCode = $row->getData('attribute_code');
        if ($attributeCode !== null) {
            $key = 'option_' . $this->getAttributeColumnRenderer()->calcOptionHash($attributeCode);
            $options[$key] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @throws LocalizedException
     */
    private function getAttributeColumnRenderer(): AttributeColumn
    {
        if ($this->attributeColumnRenderer === null) {
            $this->attributeColumnRenderer = $this->getLayout()->createBlock(
                AttributeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->attributeColumnRenderer;
    }

    /**
     * @throws LocalizedException
     */
    private function getPromptColumnRenderer(): PromptColumn
    {
        if ($this->promptColumnRenderer === null) {
            $this->promptColumnRenderer = $this->getLayout()->createBlock(
                PromptColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->promptColumnRenderer;
    }
}
