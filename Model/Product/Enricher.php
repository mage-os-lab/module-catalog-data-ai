<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Model\Config;
use Magento\Catalog\Model\Product;
use OpenAI\Factory;
use OpenAI\Client;

class Enricher
{
    private Client $client;
    public function __construct
    (
        private Factory $clientFactory,
        private Config $config
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

    /**
     * @todo move to parser class/pool
     */
    public function parsePrompt($prompt, $product): String
    {
        $prompt = preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($product) {
            return $product->getData($matches[1]);
        }, $prompt);
        return $prompt;
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

    public function execute(Product $product)
    {
        foreach ($this->getAttributes() as $attributeCode) {
            $this->enrichAttribute($product, $attributeCode);
        }
        //@TODO: throw exception?
    }
}
