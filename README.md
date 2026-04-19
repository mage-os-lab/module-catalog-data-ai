# MageOS AI Catalog Data Generation

[![Latest Stable Version](https://poser.pugx.org/mage-os/module-catalog-data-ai/v/stable)](https://packagist.org/packages/mage-os/module-catalog-data-ai)
[![License](https://poser.pugx.org/mage-os/module-catalog-data-ai/license)](https://packagist.org/packages/mage-os/module-catalog-data-ai)
[![Total Downloads](https://poser.pugx.org/mage-os/module-catalog-data-ai/downloads)](https://packagist.org/packages/mage-os/module-catalog-data-ai)

Automatically generate compelling product descriptions, meta titles, keywords, and SEO content using OpenAI's powerful language models. This Magento 2 extension seamlessly integrates AI content generation into your product workflow, improving SEO performance and customer experience while saving time on content creation.

## 🚀 Features

### **Automatic Content Generation**
- **Product Descriptions**: Generate both short and detailed product descriptions
- **SEO Meta Data**: Auto-create meta titles, descriptions, and keywords
- **Smart Prompts**: Customizable prompts with product variable substitution
- **Bulk Processing**: Mass enrich existing products with AI-generated content

### **Flexible Processing Options**
- **Real-time Generation**: Generate content immediately when products are saved
- **Asynchronous Processing**: Queue-based background processing for better performance
- **Safe Mode**: Only fill empty fields, preserving existing content
- **Override Mode**: Replace all content with fresh AI-generated versions

### **Advanced Configuration**
- **OpenAI Model Selection**: Support for all OpenAI chat completion models (GPT-3.5, GPT-4, GPT-4o, etc.)
- **Customizable Prompts**: Tailor generation prompts for each content type
- **Rate Limiting**: Built-in backoff mechanisms for API rate limits
- **Fine-tuning Controls**: Temperature, frequency penalty, and presence penalty settings

### **Review & Approval Workflow**
- **Enrichment Cache**: Hash-based lookup prevents duplicate API calls for identical inputs, saving cost and time
- **Admin Review Grid**: "AI Enrichment Review" page listing all generated content with filters, sorting, and mass actions
- **Approval Mode**: Optionally hold AI-generated content for admin review before it's written to products
- **Product Edit Indicators**: Inline status notes below enriched fields (Pending / Approved / Modified / Denied) with "View details" links

### **Also**
- **Mass Actions**: Bulk enrich products from admin grid
- **Queue Management**: Scalable async processing with Magento's queue system
- **Error Handling**: Robust error handling with retry mechanisms
- **Multi-store Support**: Configure different settings per store scope

## 📋 Requirements

- **PHP**: 8.1 or higher
- **Magento**: 2.4.x (compatible with both Magento 2 and Mage-OS)
- **OpenAI Account**: Active OpenAI account with API access
- **Composer**: For installation

## 🔧 Installation

```bash
composer require mage-os/module-catalog-data-ai
php bin/magento setup:upgrade
```

## ⚙️ Configuration

### 1. Basic Setup

Navigate to **Admin Panel → Stores → Configuration → Catalog → AI Data Enrichment**

#### Essential Settings
- **Enable Module**: Turn on/off the extension
- **OpenAI API Key**: Your OpenAI API key (required)
- **OpenAI Organization ID**: Your organization ID (optional)
- **OpenAI Project ID**: Your project ID (optional)
- **Processing Mode**: Choose between real-time or asynchronous processing

#### Model Configuration
- **OpenAI Model**: Select your preferred model (recommended: `gpt-4o`)
- **Max Tokens**: Maximum tokens per request (default: 1000)

### 2. Content Field Configuration

Under **Product Fields Auto-Generation**, configure any text-compatible product attribute for AI enrichment. The dynamic table lets admins add rows, pick a target attribute, and define its prompt. No code changes needed to enrich custom attributes.

**Attribute dropdown** is filtered to `text`, `textarea`, and `texteditor` frontend inputs.

**Default prompts** ship for `short_description` and `description`:

| Attribute | Default Prompt |
|-----------|----------------|
| `short_description` | "write a very short product description for {{name}} to highlight reasoning for purchase, under 100 words" |
| `description` | "write a detailed product description for {{name}} with features in bullet list, under 1000 words" |

Add rows for `meta_title`, `meta_keyword`, `meta_description`, or any custom attribute to extend coverage.

### 3. Advanced Settings

Fine-tune AI behavior:
- **System Prompt**: Instructions for AI behavior (default: "Be a content generator, just reply with the content, skip all introductions.")
- **Temperature**: Creativity level (0.0-1.0)
- **Frequency Penalty**: Reduce repetitive content (-2.0 to 2.0)
- **Presence Penalty**: Encourage variety (-2.0 to 2.0)

### 4. Asynchronous Processing Setup

For better performance with high-volume stores:

1. Enable **"Asynchronous enrichment"** in configuration
2. Run the queue consumer: `bin/magento queue:consumers:start mageosEnrichProductProcessor`

### 5. Enrichment Cache & Approval Workflow

Under **Settings**, two options control the review workflow:

- **Enrichment Cache**: When enabled, every generation is logged to `mageos_catalogai_product_enrichment` and keyed by a SHA-256 hash of the prompt + system prompt + attribute + store. Subsequent calls with identical input reuse the cached result instead of calling the API.
- **Approval Workflow**: Depends on the cache being enabled. Choose between `auto-approve` (apply immediately) and `require-approval` (hold generated values until an admin reviews them in **Catalog → AI Enrichment Review**).

In `require-approval` mode, new enrichments are recorded with `pending` status and are not written to products until an admin approves them from the review grid.

## 📖 Usage

### Automatic Generation (New Products)

Once configured, the extension automatically generates content for new products when saved, based on your settings:

- **Real-time Mode**: Content generated immediately during product save
- **Async Mode**: Content generated in background via queue system

### Mass Content Generation (Existing Products)

For existing products without AI-generated content:

1. Go to **Catalog → Products**
2. Select products to enrich
3. Choose from Actions dropdown:
   - **AI Enrich**: Replace all content (overwrites existing)
   - **AI Enrich (Safe)**: Only fill empty fields

### Reviewing Generated Content

When the enrichment cache is enabled, go to **Catalog → AI Enrichment Review** to see every generation. The grid supports:

- Filtering by product SKU, attribute, status, timestamps
- Opening a record to see the full prompt, edit the applied value, add notes, and change status
- Mass-approve, mass-deny, and mass-delete actions

### Product Edit Status Indicators

On the product edit form, enriched attribute fields show an inline status note beneath them:

- **Pending review** (orange): AI-generated, awaiting approval
- **Approved** (green): AI-generated and applied
- **Modified** (blue): AI-generated value was manually edited after the fact
- **Denied** (gray): Rejected by an admin

Each note includes a **View details** link that opens the full enrichment record in a new tab.

## 🤖 Supported OpenAI Models

### Recommended Models

| Model | Best For | Speed | Cost | Quality |
|-------|----------|--------|------|---------|
| **gpt-4o** | Production use | Fast | Medium | Excellent |
| **gpt-4-turbo** | High-quality content | Medium | High | Excellent |
| **gpt-3.5-turbo** | Budget-conscious | Very Fast | Low | Good |

### Model Requirements
- Must support **Chat Completions API**
- Must support **developer** role messages
- Recommended: Models with function calling capability

## 🎯 Prompt Customization

### Using Product Variables

Prompts support dynamic variables from product data:

```
Write a description for {{name}} priced at {{price}}. Key features: {{short_description}}
```

### Available Variables

Any product attribute code can be referenced as `{{attribute_code}}` and will be substituted with that attribute's current value at generation time. Common examples:

- `{{name}}`: Product name
- `{{price}}`: Product price
- `{{sku}}`: Product SKU
- `{{short_description}}`: Existing short description
- `{{meta_keyword}}`: Existing keywords
- Custom attributes: any EAV attribute on the product

### Best Practices for Prompts

1. **Be Specific**: Include detailed instructions about tone, length, and format
2. **Use Context**: Reference product attributes to create relevant content
3. **Set Constraints**: Specify word/character limits
4. **Define Format**: Request bullet points, paragraphs, or specific structures

Example optimized prompt:
```
Create a compelling product description for {{name}}:
- Target audience: [your customer type]
- Tone: Professional yet engaging
- Length: 150-200 words
- Include key benefits and features
- End with a call-to-action
- Focus on {{category}} category specifics
```

## 🔧 Troubleshooting

### Common Issues

**API Key Errors**
- Verify your OpenAI API key is correct and active
- Check your OpenAI account has sufficient credits
- Ensure API key has appropriate permissions

**Rate Limiting**
- The extension includes automatic backoff mechanisms
- Consider using async processing for bulk operations
- Monitor your OpenAI usage dashboard

**Content Not Generating**
- Ensure the module is enabled in configuration
- Check that product fields are empty (in safe mode)
- Verify prompts are configured for the desired attributes

**Queue Processing Issues**
- Ensure queue consumers are running
- Check Magento cron is functioning
- Monitor queue status in admin panel

### Performance Optimization

1. **Use Async Processing** for bulk operations
2. **Optimize Prompts** or choose a faster model to reduce token usage
3. **Configure Rate Limits** appropriately
4. **Monitor API Costs** regularly

## 🤝 Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Author**: Ryan Sun (ryansun81@gmail.com)
- **Website**: [Sunmerce](https://www.sunmerce.com/)
- **Repository**: [mage-os-lab/module-catalog-data-ai](https://github.com/mage-os-lab/module-catalog-data-ai)

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/mage-os-lab/module-catalog-data-ai/issues)
- **Documentation**: This README and inline code comments
- **Community**: [Mage-OS Discord](http://chat.mage-os.org)
