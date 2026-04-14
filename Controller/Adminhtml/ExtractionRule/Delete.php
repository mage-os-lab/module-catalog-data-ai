<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Delete extends Action implements HttpGetActionInterface
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
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');

        if ($ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);
            if ($rule->getId()) {
                try {
                    $this->ruleResource->delete($rule);
                    $this->messageManager->addSuccessMessage(__('The rule has been deleted.'));
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            }
        }

        return $redirect;
    }
}
