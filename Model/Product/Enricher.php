<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Api\AiClientInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\ProductContextBuilderInterface;
use MageOS\CatalogDataAI\Model\Config;

class Enricher
{
    public function __construct(
        private readonly AiClientInterface $aiClient,
        private readonly Config $config,
        private readonly HashGenerator $hashGenerator,
        private readonly EnrichmentRecorder $enrichmentRecorder,
        private readonly ProductContextBuilderInterface $contextBuilder
    ) {}

    public function getAttributes(?int $storeId = null): array
    {
        return $this->config->getConfiguredAttributes($storeId);
    }

    public function parsePrompt(string $prompt, Product $product): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($product) {
            return (string) ($product->getData($matches[1]) ?? '');
        }, $prompt);
    }

    public function execute(Product $product): void
    {
        $pending = $this->collectPending($product);
        if (empty($pending)) {
            return;
        }

        $results = $this->generate($product, $pending);

        $this->record($product, $results, $pending);
    }

    /**
     * Check cache and collect attributes that need AI generation.
     * Applies cached values directly to the product as a side effect.
     *
     * @return array<string, array{prompt: string, hash: ?string}>
     */
    private function collectPending(Product $product): array
    {
        $storeId = (int) $product->getStoreId();
        $systemPrompt = (string) $this->config->getSystemPrompt();
        $pending = [];

        foreach ($this->getAttributes($storeId) as $code) {
            if (!$product->getData('mageos_catalogai_overwrite') && $product->getData($code)) {
                continue;
            }

            $prompt = $this->config->getProductPrompt($code, $storeId);
            if (!$prompt) {
                continue;
            }

            $parsedPrompt = $this->parsePrompt($prompt, $product);

            if (!$this->config->isCacheEnabled()) {
                $pending[$code] = ['prompt' => $parsedPrompt, 'hash' => null];
                continue;
            }

            $hash = $this->hashGenerator->generate($parsedPrompt, $systemPrompt, $code, $storeId);
            $existing = $this->enrichmentRecorder->findByHash($hash, $code, $storeId);

            if ($existing !== null) {
                $this->applyCachedValue($product, $code, $existing);
                continue;
            }

            $pending[$code] = ['prompt' => $parsedPrompt, 'hash' => $hash];
        }

        return $pending;
    }

    private function applyCachedValue(
        Product $product,
        string $code,
        EnrichmentInterface $enrichment
    ): void {
        $status = $enrichment->getStatus();
        if ($status === EnrichmentInterface::STATUS_APPROVED
            || $status === EnrichmentInterface::STATUS_APPLIED
        ) {
            $product->setData(
                $code,
                $enrichment->getAppliedValue() ?? $enrichment->getGeneratedValue()
            );
        }
    }

    /**
     * Call AI for all pending attributes in a single batch.
     * Falls back to individual calls if batch returns empty.
     *
     * @param array<string, array{prompt: string, hash: ?string}> $pending
     * @return array<string, string>
     */
    private function generate(Product $product, array $pending): array
    {
        $systemPrompt = (string) $this->config->getSystemPrompt();
        $prompts = array_map(fn(array $item) => $item['prompt'], $pending);

        $results = $this->aiClient->generateBatch(
            $systemPrompt,
            $this->contextBuilder->build($product),
            $prompts
        );

        if (!empty($results)) {
            return $results;
        }

        // Batch failed — fall back to individual calls
        $results = [];
        foreach ($prompts as $code => $prompt) {
            $value = $this->aiClient->generate($systemPrompt, $prompt);
            if ($value !== null) {
                $results[$code] = $value;
            }
        }

        return $results;
    }

    /**
     * Record enrichments and apply values to the product.
     *
     * @param array<string, string> $results
     * @param array<string, array{prompt: string, hash: ?string}> $pending
     */
    private function record(Product $product, array $results, array $pending): void
    {
        $storeId = (int) $product->getStoreId();

        foreach ($results as $code => $value) {
            if (!isset($pending[$code])) {
                continue;
            }

            $hash = $pending[$code]['hash'];
            if ($hash !== null) {
                $this->recordEnrichment(
                    $product, $code, $hash, $pending[$code]['prompt'], $value, $storeId
                );
            }

            if (!$this->config->isApprovalRequired()) {
                $product->setData($code, $value);
            }
        }
    }

    private function recordEnrichment(
        Product $product,
        string $code,
        string $hash,
        string $parsedPrompt,
        string $value,
        int $storeId
    ): void {
        $productId = (int) $product->getId();

        if ($productId > 0) {
            $this->enrichmentRecorder->record(
                $productId, $storeId, $code, $hash, $parsedPrompt, $value
            );
            return;
        }

        // Deferred: product not yet saved, persist after save via observer
        $deferred = $product->getData('mageos_catalogai_deferred_enrichments') ?? [];
        $deferred[] = [
            'attribute_code' => $code,
            'prompt_hash' => $hash,
            'parsed_prompt' => $parsedPrompt,
            'generated_value' => $value,
            'store_id' => $storeId,
        ];
        $product->setData('mageos_catalogai_deferred_enrichments', $deferred);
    }
}
