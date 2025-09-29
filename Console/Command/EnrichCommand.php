<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Console\Command;

use MageOS\CatalogDataAI\Model\Product\Enricher;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to enrich product data using AI
 */
class EnrichCommand extends Command
{
    private const PRODUCT_ID_ARGUMENT = 'product_id';
    private const OVERWRITE_ARGUMENT = 'overwrite';

    public function __construct(
        private readonly Enricher $enricher,
        private readonly ProductRepository $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly AppState $appState,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('mageos-catalog-ai:enrich')
            ->setDescription('Enrich product data using AI')
            ->addArgument(
                self::PRODUCT_ID_ARGUMENT,
                InputArgument::REQUIRED,
                'Product ID to enrich'
            )
            ->addArgument(
                self::OVERWRITE_ARGUMENT,
                InputArgument::OPTIONAL,
                'Overwrite existing data (true|false)',
                'false'
            );
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $productId = (int) $input->getArgument(self::PRODUCT_ID_ARGUMENT);
        $overwriteArg = (string) $input->getArgument(self::OVERWRITE_ARGUMENT);

        // Parse overwrite argument
        $overwrite = $this->parseOverwriteFlag($overwriteArg);

        $output->writeln("<info>Starting product enrichment for Product ID: {$productId}</info>");
        $output->writeln("<info>Overwrite existing data: " . ($overwrite ? 'true' : 'false') . "</info>");

        try {
            // Set area code to adminhtml for proper context
            $this->appState->setAreaCode('adminhtml');

            // Set store to admin (same as Consumer)
            $this->storeManager->setCurrentStore(0);

            // Load the product
            $product = $this->productRepository->getById($productId);

            $output->writeln("<info>Loaded product: {$product->getName()}</info>");

            // Set the overwrite flag (same as Consumer)
            $product->setData('mageos_catalogai_overwrite', $overwrite);

            // Execute the enrichment (same as Consumer)
            $this->enricher->execute($product);

            // Save the product (same as Consumer)
            $this->productRepository->save($product);

            $output->writeln("<info>Product enrichment completed successfully!</info>");

            return Cli::RETURN_SUCCESS;

        } catch (NoSuchEntityException $e) {
            $output->writeln("<error>Product with ID {$productId} not found.</error>");
            return Cli::RETURN_FAILURE;
        } catch (\Exception $e) {
            $output->writeln("<error>Error enriching product: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Parse the overwrite flag from string input
     */
    private function parseOverwriteFlag(string $overwriteArg): bool
    {
        $normalizedArg = strtolower(trim($overwriteArg));

        return in_array($normalizedArg, ['true', '1', 'yes', 'y'], true);
    }

}
