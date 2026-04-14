<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use MageOS\CatalogDataAI\Model\Product\Enricher;

class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Enricher $enricher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sku = $this->getRequest()->getParam('sku');
        $prompt = $this->getRequest()->getParam('prompt');

        if (!$sku || !$prompt) {
            return $result->setData([
                'success' => false,
                'message' => 'SKU and prompt are required.',
            ]);
        }

        try {
            $product = $this->productRepository->get($sku);
            $resolvedPrompt = $this->enricher->parsePrompt($prompt, $product);

            return $result->setData([
                'success' => true,
                'resolved_prompt' => $resolvedPrompt,
                'product_name' => $product->getName(),
            ]);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $result->setData([
                'success' => false,
                'message' => sprintf('Product with SKU "%s" not found.', $sku),
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
