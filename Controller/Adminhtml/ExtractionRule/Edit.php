<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::extraction_rules');
        $resultPage->getConfig()->getTitle()->prepend(
            $ruleId ? __('Edit Extraction Rule: %1', $rule->getData('name')) : __('New Extraction Rule')
        );

        return $resultPage;
    }
}
