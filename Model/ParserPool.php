<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use MageOS\CatalogDataAI\Api\ParserInterface;
use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;

/**
 * Parser pool for managing and resolving attribute parsers
 *
 * Manages a collection of parsers and resolves the most appropriate parser
 * for each attribute based on priority and capability.
 */
class ParserPool
{
    /**
     * @var ParserInterface[]
     */
    private array $parsers = [];

    /**
     * @var ParserInterface[]
     */
    private array $sortedParsers = [];

    /**
     * @var bool
     */
    private bool $sorted = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        array $parsers = []
    ) {
        foreach ($parsers as $parser) {
            $this->addParser($parser);
        }
    }

    /**
     * Add a parser to the pool
     *
     * @param ParserInterface $parser
     * @return void
     */
    public function addParser(ParserInterface $parser): void
    {
        $this->parsers[] = $parser;
        $this->sorted = false; // Mark as unsorted
    }

    /**
     * Get the appropriate parser for an attribute
     *
     * @param string $attributeCode
     * @param Product $product
     * @return ParserInterface|null
     */
    public function getParser(string $attributeCode, Product $product): ?ParserInterface
    {
        $this->sortParsers();

        foreach ($this->sortedParsers as $parser) {
            try {
                if ($parser->canParse($attributeCode, $product)) {
                    $this->logger->debug(
                        'Selected parser for attribute',
                        [
                            'attribute_code' => $attributeCode,
                            'parser_class' => get_class($parser),
                            'priority' => $parser->getPriority()
                        ]
                    );
                    return $parser;
                }
            } catch (\Exception $e) {
                $this->logger->warning(
                    'Parser failed canParse check',
                    [
                        'attribute_code' => $attributeCode,
                        'parser_class' => get_class($parser),
                        'exception' => $e->getMessage()
                    ]
                );
                // Continue to next parser
            }
        }

        $this->logger->warning(
            'No suitable parser found for attribute',
            ['attribute_code' => $attributeCode]
        );

        return null;
    }

    /**
     * Parse an attribute value using the appropriate parser
     *
     * @param string $attributeCode
     * @param Product $product
     * @return string
     */
    public function parseAttribute(string $attributeCode, Product $product): string
    {
        $parser = $this->getParser($attributeCode, $product);

        if (!$parser) {
            // Fallback to raw product data if no parser found
            $value = $product->getData($attributeCode);
            return $value !== null ? (string) $value : '';
        }

        try {
            return $parser->parse($attributeCode, $product);
        } catch (\Exception $e) {
            $this->logger->error(
                'Parser failed during parse operation',
                [
                    'attribute_code' => $attributeCode,
                    'product_id' => $product->getId(),
                    'parser_class' => get_class($parser),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );

            // Fallback to raw product data
            $value = $product->getData($attributeCode);
            return $value !== null ? (string) $value : '';
        }
    }

    /**
     * Get all registered parsers (sorted by priority)
     *
     * @return ParserInterface[]
     */
    public function getAllParsers(): array
    {
        $this->sortParsers();
        return $this->sortedParsers;
    }

    /**
     * Get parser statistics for debugging
     *
     * @return array
     */
    public function getParserInfo(): array
    {
        $info = [];
        foreach ($this->getAllParsers() as $parser) {
            $info[] = [
                'class' => get_class($parser),
                'priority' => $parser->getPriority()
            ];
        }
        return $info;
    }

    /**
     * Sort parsers by priority (highest first)
     *
     * @return void
     */
    private function sortParsers(): void
    {
        if ($this->sorted) {
            return;
        }

        $this->sortedParsers = $this->parsers;

        // Sort by priority (highest first)
        usort($this->sortedParsers, function (ParserInterface $a, ParserInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->sorted = true;
    }
}
