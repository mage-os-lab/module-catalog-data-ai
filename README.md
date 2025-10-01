# module-catalog-data-ai

Automatically Generate product descriptions and meta keywords with openAI, improve SEO and customer experience with the help of AI

## How to install

`composer require mage-os/module-catalog-data-ai`

## How to use

- add your openAI [api key](https://platform.openai.com/api-keys) to the configuration and enable the module, under menu `Store config -> Catalog -> AI Data enrichment`
- that's it, watch AI writing content every time a new product is created
- optionally, you can customize prompt, model or settings in the config screen to get you better result

## Which OpenAI model should I use?

TL;DR: use `gpt-4o`

You can use any [model](https://platform.openai.com/docs/guides/text?api-mode=chat#choosing-a-model) that supports chat completion api, as long as they support `developer` role message

## Security Warning

⚠️ **Important**: When Magento is running in developer mode, this module will log the complete prompts sent to the OpenAI API to the debug log. This may include sensitive product data such as names, descriptions, prices, and other product attributes. **Do not enable developer mode or debug logging on production environments** as this could expose sensitive business information in log files.
