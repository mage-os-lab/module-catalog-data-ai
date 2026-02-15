# Design: Flag Enriched Product Attributes on Product Edit

**Issue:** mage-os-lab/module-catalog-data-ai#48
**Date:** 2026-02-15

## Problem

When editing a product in the admin, there is no indication of which attributes were AI-generated, their enrichment status (pending review, approved, modified), or a way to navigate to the enrichment review record.

## Solution

Add inline status notes below each enriched attribute field on the product edit form, using Magento's `additionalInfo` field config. Each note shows the enrichment status with a colored label and a "View details" link to the enrichment edit form.

## Architecture

### Mechanism: Product Form Modifier

A `ModifierInterface` implementation registered in `Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Pool` via `etc/adminhtml/di.xml`. Runs after the EAV modifier (sortOrder 200).

### Data Flow

1. Admin opens product edit page
2. `ProductDataProvider` calls all modifiers in pool order
3. `EnrichmentStatus::modifyMeta()` runs:
   - Gets product ID from HTTP request
   - Queries `catalogai_product_enrichment` for all records matching this product + store
   - Groups by `attribute_code`, takes most recent per attribute
   - For each enrichment, injects `additionalInfo` HTML into the field's meta
4. Magento's `form/field.html` template renders the HTML below the input

### Status Determination

Given an enrichment record and the current product attribute value:

| Record Status | Value Match | Display | Color |
|---------------|-------------|---------|-------|
| `pending` | n/a | AI-generated - Pending review | Orange |
| `approved` or `applied` | Current value = applied/generated value | AI-generated - Approved | Green |
| `approved` or `applied` | Current value differs | AI-generated - Modified | Blue |
| `denied` | n/a | AI-generated - Denied | Gray |

"Value match" compares the product's current attribute value against the enrichment's `applied_value` (or `generated_value` if no `applied_value` set).

### Guard Conditions

- If enrichment cache is disabled: modifier is a no-op (no enrichment records exist)
- If product is new (no product_id in request): no-op
- If no enrichment records for this product: no-op

## Files

### New Files

| File | Purpose |
|------|---------|
| `Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php` | Product form modifier |
| `view/adminhtml/web/css/source/_module.less` | Status note color styles |

### Modified Files

| File | Change |
|------|--------|
| `etc/adminhtml/di.xml` | Register modifier in `Modifier\Pool` (sortOrder 200) |

### No New Files Needed

- No JS components (uses existing `additionalInfo` HTML rendering)
- No layout XML (modifier pool handles injection)
- No templates (Magento's `form/field.html` already renders `additionalInfo`)

## Component: EnrichmentStatus Modifier

```
class EnrichmentStatus extends AbstractModifier

Constructor Dependencies:
- EnrichmentRepositoryInterface  — query enrichment records
- SearchCriteriaBuilder          — build product_id + store_id filters
- RequestInterface               — get product_id from URL params
- Config                         — check if enrichment cache is enabled
- UrlInterface                   — build admin URLs for "View details" link
- StoreManagerInterface          — get current store context
- ProductRepositoryInterface     — load product for value comparison

modifyData(array $data): array
    → return $data unchanged

modifyMeta(array $meta): array
    → early return if cache disabled or no product_id
    → load product, query enrichments for product + store
    → group by attribute_code (most recent wins)
    → for each enrichment:
        → determine status (pending/approved/modified/denied)
        → build HTML: <span class="catalogai-enrichment-note--{status}">label <a>View details</a></span>
        → locate field container in meta tree, set additionalInfo
    → return modified meta
```

## HTML Output

```html
<span class="catalogai-enrichment-note catalogai-enrichment-note--pending">
    AI-generated &middot; Pending review &mdash;
    <a href=".../catalogai/enrichment/edit/id/42" target="_blank">View details</a>
</span>
```

## LESS Styles

Uses Magento admin theme LESS variables from `web/mui/styles/_vars.less` and `lib/web/css/source/lib/variables/_colors.less` for consistency with the admin UI:

```less
// view/adminhtml/web/css/source/_module.less
.catalogai-enrichment-note {
    font-size: 1.2rem;
    margin-top: 4px;

    a {
        margin-left: 4px;
    }

    &--pending { color: @grid-severity-minor-color; }     // orange (#ed4f2e)
    &--approved { color: @grid-severity-notice-color; }    // green (#185b00)
    &--modified { color: @theme__color__primary; }         // blue (#1979c3)
    &--denied { color: @color-gray60; }                    // gray (#999)
}
```

Variable mapping:
- **Pending** → `@grid-severity-minor-color` (admin orange, used for warnings/minor severity)
- **Approved** → `@grid-severity-notice-color` (admin green, used for success/notice severity)
- **Modified** → `@theme__color__primary` (admin primary blue)
- **Denied** → `@color-gray60` (standard admin gray)

## Testing

### Unit Test

`Test/Unit/Ui/DataProvider/Product/Form/Modifier/EnrichmentStatusTest.php`

- Mock repository to return enrichment records with various statuses
- Mock product repository to return product with known attribute values
- Verify `modifyMeta()` injects correct `additionalInfo` HTML for each status
- Verify no modification when cache disabled
- Verify no modification for new products (no product_id)
- Verify "modified" detection when product value differs from applied value

### Manual Test Plan

1. Enable enrichment cache in system config
2. Enrich a product, verify "Pending review" (or "Approved") note appears on re-edit
3. Approve enrichment in review grid, verify note updates to "Approved" on product edit
4. Manually edit the attribute value, save, verify note shows "Modified"
5. Deny an enrichment, verify "Denied" note
6. Click "View details" link, verify it opens enrichment edit form
7. Disable cache, verify no notes appear
8. Create new product, verify no notes on initial edit

## YAGNI Notes

- No inline approve/deny buttons (use existing review grid)
- No enrichment history timeline (one note per attribute, most recent record)
- No notification badges or counts (just the status note)
- No real-time updates (status reflects state at page load)
