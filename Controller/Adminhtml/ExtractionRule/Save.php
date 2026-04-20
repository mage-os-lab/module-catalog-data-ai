<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function __construct(
        Action\Context $context,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getRequest()->getPostValue();
        $redirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $redirect->setPath('*/*/');
        }

        $ruleId = (int)($data['rule_id'] ?? 0);
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $redirect->setPath('*/*/');
            }
        }

        if (isset($data['store_ids']) && is_array($data['store_ids'])) {
            $data['store_ids'] = implode(',', $data['store_ids']);
        }

        if (isset($data['source_attributes']) && is_array($data['source_attributes'])) {
            $data['source_attributes'] = implode(',', $data['source_attributes']);
        }

        if (isset($data['rule']['conditions'])) {
            $rule->loadPost(['conditions' => $data['rule']['conditions']]);
        }

        $rule->addData($data);

        try {
            $this->ruleResource->save($rule);
            $this->messageManager->addSuccessMessage(__('The extraction rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $rule->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('*/*/edit', ['rule_id' => $ruleId]);
        }
    }
}
