<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Model\Config;
use Magento\Catalog\Model\Product;
use OpenAI\Factory;
use OpenAI\Client;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Exceptions\ErrorException;

class Enricher
{
    private Client $client;
    public function __construct
    (
        private readonly Factory $clientFactory,
        private readonly Config $config
    ) {
        $this->client = $this->clientFactory
            ->withApiKey($this->config->getApiKey())
            ->make();
    }

    public function getAttributes(): array
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
    public function parsePrompt($prompt, $product): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($product) {
            return $product->getData($matches[1]);
        }, $prompt);
    }

    public function enrichAttribute($product, $attributeCode): void
    {
        if(!$product->getData('mageos_catalogai_overwrite') && $product->getData($attributeCode)){
            return;
        }
        if($prompt = $this->config->getProductPrompt($attributeCode)) {

            $prompt = $this->parsePrompt($prompt, $product);

            $response = $this->client->chat()->create([
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
            ]);

            // @TODO:  no exception?
            if($result = $response->choices[0]) {
                $product->setData($attributeCode, $result->message?->content);
            }
            $this->backoff($response->meta());
        }
    }

    public function backoff(MetaInformation $meta): void
    {
        if($meta->requestLimit->remaining < 1) {
            sleep($this->strToSeconds($meta->requestLimit->reset));
        }
        // 1 token ~= 0.75 word
        // do not use config value
        if($meta->tokenLimit->remaining < 1000) {
            sleep($this->strToSeconds($meta->tokenLimit->reset));
        }
    }

    private function strToSeconds(string $time): float|int
    {
        preg_match('/(?:([0-9]+)h)?(?:([0-9]+)m)?(?:([0-9]+)s)?/', $time, $matches);

        $hours = isset($matches[1]) ? intval($matches[1]) : 0;
        $minutes = isset($matches[2]) ? intval($matches[2]) : 0;
        $seconds = isset($matches[3]) ? intval($matches[3]) : 0;

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    public function execute(Product $product): void
    {
        foreach ($this->getAttributes() as $attributeCode) {
            try {
                $this->enrichAttribute($product, $attributeCode);
            } catch (ErrorException $e) {
                // try it one more time just in case we failed to catch the limit in backoff
                sleep(60);
                $this->enrichAttribute($product, $attributeCode);
            }
        }
        //@TODO: throw exception?
    }
}
