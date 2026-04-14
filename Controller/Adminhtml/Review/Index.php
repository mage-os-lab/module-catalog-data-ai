<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Review;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::enrichment_review');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Enrichment Review'));
        return $resultPage;
    }
}
