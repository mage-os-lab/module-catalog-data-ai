<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\PromptRule;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $ruleId = (int)$this->request->getParam('rule_id');
        if ($ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);

            if ($rule->getRuleId()) {
                $data = $rule->getData();
                if (isset($data['store_ids']) && is_string($data['store_ids'])) {
                    $data['store_ids'] = explode(',', $data['store_ids']);
                }
                $this->loadedData[$ruleId] = $data;
            }
        }

        return $this->loadedData;
    }
}
