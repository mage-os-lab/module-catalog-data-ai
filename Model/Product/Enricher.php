<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Model\Config;
use Magento\Catalog\Model\Product;
use OpenAI\Factory;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateResponseChoice;

class Enricher
{
    private Client $client;

    public function __construct(
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
            'description',
            'meta_title',
            'meta_keyword',
            'meta_description',
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

    public function getChatGptResponse($prompt, $product): CreateResponse
    {
        return $this->client->chat()->create(
            [
                'model' => $this->config->getApiModel(),
                'temperature' => $this->config->getTemperature(),
                'frequency_penalty' => $this->config->getFrequencyPenalty(),
                'presence_penalty' => $this->config->getPresencePenalty(),
                'max_tokens' => $this->config->getApiMaxTokens(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->config->getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->parsePrompt($prompt, $product)
                    ]
                ]
            ]
        );
    }

    public function enrichAttribute($product, $attributeCode): ?CreateResponseChoice
    {
        $prompt = $this->config->getProductPrompt($attributeCode);
        if ($prompt === null) {
            return null;
        }

        $prompt = $this->parsePrompt($prompt, $product);

        $response = $this->getChatGptResponse($prompt, $product);
        return $response->choices[0] ?? null;
    }

    public function execute(Product $product)
    {
        foreach ($this->getAttributes() as $attributeCode) {
            $responseResult = $this->enrichAttribute($product, $attributeCode);

            $product->setData($attributeCode, $responseResult);
        }

        //@TODO: throw exception?
    }
}
