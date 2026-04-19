<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\AbstractBlock;

class PromptColumn extends AbstractBlock
{
    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    public function setColumnName(string $value): self
    {
        return $this->setData('column_name', $value);
    }

    protected function _toHtml(): string
    {
        return sprintf(
            '<textarea id="%s" name="%s" class="%s" style="width:100%%; min-height:80px;">%s</textarea>',
            $this->escapeHtmlAttr($this->getId()),
            $this->escapeHtmlAttr($this->getName()),
            'required-entry',
            $this->escapeHtml($this->getValue() ?? '')
        );
    }
}
