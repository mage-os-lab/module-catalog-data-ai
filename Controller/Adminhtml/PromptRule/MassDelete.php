<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly PromptRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;

        foreach ($collection as $rule) {
            $this->ruleResource->delete($rule);
            $deleted++;
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 rule(s) have been deleted.', $deleted));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
