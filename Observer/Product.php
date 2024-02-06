<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use MageOS\CatalogDataAI\Model\Config;
use OpenAI\Factory;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

class Product implements ObserverInterface
{
    private Client $client;
    public function __construct(
        private Factory $clientFactory,
        private Config $config,
        private LoggerInterface $logger
    ) {
        $this->client = $this->clientFactory->withApiKey($this->config->getApiKey())
            ->make();
    }

    public function getAttributes()
    {
        return [
            'short_description',
            'description'
        ];
    }

    public function enrichAttribute($product, $attributeCode)
    {
        if($product->getData($attributeCode)) {
            return;
        }
        if($prompt = $this->config->getProductPrompt($attributeCode)) {

            $prompt = $this->parsePrompt($prompt, $product);

            $response = $this->client->completions()->create([
                'model' => $this->config->getApiModel(),
                'prompt' => $this->parsePrompt($prompt, $product),
                'max_tokens' => $this->config->getApiMaxTokens(),
                'temperature' => 0.5
            ]);

            // @TODO:  no exception?
            if($result = $response->choices[0]) {
                $product->setData($attributeCode, $result->text);
            }
        }
    }

    /**
     * @todo move to parser class/pool
     */
    public function parsePrompt($prompt, $product): String
    {
        return str_replace(
            ['{{name}}', '{{sku}}'],
            [$product->getName(), $product->getSku()],
            $prompt
        );
    }

    public function execute(Observer $observer): void
    {
        if(!$this->config->isEnabled() || !$this->config->getApiKey()) {
            return;
        }
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getProduct();

        // only enrich new products
//        if(!$product->isObjectNew()) {
//            return;
//        }

        foreach ($this->getAttributes() as $attributeCode) {
            $this->enrichAttribute($product, $attributeCode);
        }
    }
}
