# MageOS Catalog Data AI - CLI Command

## Overview

This module now includes a CLI command to initiate product enrichment using AI, providing the same functionality as the message queue consumer but through a direct command-line interface.
This feature was added to aid in debug of AI calls where message queue fails with status 4 or 6

```
$ php -d start_with_request=yes ./bin/magento mageos-catalog-ai:enrich 82511
Starting product enrichment for Product ID: 82511
Overwrite existing data: false
Loaded product: BenQ Joybook R56 Series R56-BV04 Replacement Laptop Screen Panel
Error enriching product: The "Manufacturer" attribute value is empty. Set the attribute and try again.
```

The above error was not logged during cron runs, but was logged during the CLI command.

After successful:

```
Starting product enrichment for Product ID: 82511
Overwrite existing data: false
Loaded product: BenQ Joybook R56 Series R56-BV04 Replacement Laptop Screen Panel
Product enrichment completed successfully!
```

## Command Usage

```bash
./bin/magento mageos-catalog-ai:enrich [product_id] [overwrite]
```

### Parameters

- `product_id` (required): The ID of the product to enrich
- `overwrite` (optional): Whether to overwrite existing data. Accepts: `true`, `false`, `1`, `0`, `yes`, `no`, `y`, `n`. Default: `false`

### Examples

```bash
# Enrich product with ID 104057, preserve existing data
./bin/magento mageos-catalog-ai:enrich 104057

# Enrich product with ID 104057, overwrite existing data
./bin/magento mageos-catalog-ai:enrich 104057 true

# Alternative overwrite formats
./bin/magento mageos-catalog-ai:enrich 104057 yes
./bin/magento mageos-catalog-ai:enrich 104057 1
```

## Implementation Details

### Files Added

1. **Console/Command/EnrichCommand.php** - Main CLI command class
2. **etc/di.xml** - Dependency injection configuration to register the command

### Functionality

The CLI command replicates the exact same logic as the message queue consumer:

1. Sets the store context to admin (store ID 0)
2. Loads the product by ID
3. Sets the overwrite flag on the product
4. Executes the AI enrichment process
5. Saves the product with enriched data

### Error Handling

The command includes comprehensive error handling:

- **Product Not Found**: Returns appropriate error message if product ID doesn't exist
- **Enrichment Errors**: Catches and displays any errors during the AI enrichment process
- **Invalid Parameters**: Validates and parses input parameters

### Attributes Enriched

The command enriches the following product attributes using AI:

- `short_description`
- `description`
- `meta_title`
- `meta_keyword`
- `meta_description`

### Requirements

- Product must have required attributes (e.g., manufacturer) populated for AI enrichment to work
- OpenAI API configuration must be properly set up in the module configuration
- Sufficient API credits/quota for OpenAI requests

## Technical Notes

- The command uses the same `Enricher` class as the message queue consumer
- Rate limiting and backoff logic from the original enricher are preserved
- Command follows Magento 2 CLI command best practices
- Proper dependency injection for testability and maintainability
