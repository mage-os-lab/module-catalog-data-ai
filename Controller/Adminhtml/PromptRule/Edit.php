<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getRuleId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::prompt_rules');
        $resultPage->getConfig()->getTitle()->prepend(
            $ruleId ? __('Edit Rule: %1', $rule->getName()) : __('New Prompt Rule')
        );

        return $resultPage;
    }
}
