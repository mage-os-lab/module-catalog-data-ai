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

## Troubleshooting

### You get message that enrichment is queued: 'A total of 2 record(s) are scheduled to get data enriched.', but nothing happens.

- Check that your message queue has entries for the enrichment.
- If there are multiple queued messages, it is likely your message queue consumer is not running.
- It depends here on your environment / site setup.

You may simply need to adjust your consumer setup in env.php, example below uses cron for consumer runs, and added ''mageosEnrichProductProcessor' consumer.

```
'cron_consumers_runner' => [
        'cron_run' => true,
        'max_messages' => 2000,
        'consumers' => [
            'product_action_attribute.update',
            'product_action_attribute.website.update',
            'exportProcessor',
            'codegeneratorProcessor',
            'async.operations.all',
            'mageosEnrichProductProcessor',
        ]
    ],
    'queue' => [
        // For cron-run consumers, do NOT wait for messages (let cron re-spawn them)
        'consumers_wait_for_messages' => 0,
    ],
```
 
