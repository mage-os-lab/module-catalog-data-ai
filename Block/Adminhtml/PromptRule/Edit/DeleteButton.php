<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\PromptRule\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getButtonData(): array
    {
        $ruleId = (int)$this->request->getParam('rule_id');
        if (!$ruleId) {
            return [];
        }

        return [
            'label' => __('Delete Rule'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Are you sure you want to delete this rule?'),
                $this->urlBuilder->getUrl('*/*/delete', ['rule_id' => $ruleId])
            ),
            'sort_order' => 20,
        ];
    }
}
