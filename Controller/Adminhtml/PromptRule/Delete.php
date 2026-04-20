<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');

        if (!$ruleId) {
            $this->messageManager->addErrorMessage(__('Rule ID is required.'));
            return $redirect;
        }

        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, $ruleId);

        if (!$rule->getRuleId()) {
            $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
            return $redirect;
        }

        try {
            $this->ruleResource->delete($rule);
            $this->messageManager->addSuccessMessage(__('The rule has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect;
    }
}
