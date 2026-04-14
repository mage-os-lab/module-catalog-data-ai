# Feature Roadmap Design — MageOS_CatalogDataAI

**Date:** 2026-04-14
**Approach:** Quick Wins, Then Depth
**BC policy:** Pragmatic — data patches for migration, old paths can break, documented in release notes

---

## Phase 1: Housekeeping + Configurable Attributes

**Issues:** #23, #22, #25, #27

### Housekeeping

- Run PHP-CS-Fixer with PSR-12/Magento rules across all files
- Fix `system.xml` temperature field: change label from "System Prompt" to "Temperature", add `<comment>` tags to all advanced fields explaining valid ranges
- Fix `composer.json`: add explicit Magento module dependencies (`magento/framework`, `magento/module-catalog`, `magento/module-ui`, `magento/module-backend`), remove the mirror repository entry, keep `phpunit` in require-dev

### Configurable Attributes

Replace the hardcoded attribute list and static `system.xml` fields with a dynamic row configuration.

- **New admin config**: Replace the 5 fixed textarea fields under "Product Fields Auto-Generation" with a dynamic row table with columns:
  - **Attribute** — dropdown of product text/textarea attributes
  - **Prompt** — textarea for the prompt template
  - **Enabled** — yes/no toggle per attribute
- **Data patch**: Migrate existing `catalog_ai/product/*` config values into the new dynamic row format
- **Enricher refactor**: `getAttributes()` reads from the dynamic config instead of returning a hardcoded array. `enrichAttribute()` stays the same — it already takes an attribute code and looks up the prompt from config
- **Config model**: `getProductPrompt()` adapts to read from the serialized dynamic row data instead of individual config paths
- **Default rows**: Pre-populate the same 5 attributes (short_description, description, meta_title, meta_keyword, meta_description) with their current default prompts

---

## Phase 2: Multi-Store/Locale + Enrichment Status Flags

**Issues:** #12, #48

### Multi-Store/Locale

- **Locale-aware prompts**: Detect the store's locale (`general/locale/code`) and prepend "Respond in {locale language}" to the system prompt when enriching for a store
- **Store-scoped enrichment**: `Publisher`/`Request` DTO gets a `storeId` field. `Consumer` sets the correct store scope before enriching so config values resolve per-store. Mass actions enrich for the selected store scope
- **Data patch**: Add `storeId` to the `Request` queue message format with default of `0` for backward compatibility with in-flight messages

### Enrichment Status Flags

- **New DB table**: `mageos_catalogai_enrichment_log` with columns: `entity_id` (product ID), `attribute_code`, `store_id`, `status` (enum: `generated`, `pending_review`, `modified`), `generated_at`, `updated_at`
- **Write on enrich**: When `Enricher` successfully sets an attribute value, write/update a log row with status `generated`
- **Detect manual edits**: In `SaveBefore` observer, if an enriched attribute's value changed and it wasn't the enricher doing it, flip status to `modified`
- **Admin UI indicator**: On the product edit form, add a small badge/note next to enriched fields showing their status via a UI component modifier
- No blocking review workflow yet — that's Phase 4. This phase just tracks and displays status.

---

## Phase 3: Prompt Rules Engine + Config Migration

**Issues:** #32, #43

### Prompt Rules Engine

- **New admin grid**: "AI Prompt Rules" grid. Each rule has:
  - **Name** — human-readable label
  - **Attribute** — which attribute this prompt targets
  - **Store scope(s)** — multiselect, default: all
  - **Conditions** — product conditions using Magento's existing condition engine (same as catalog price rules)
  - **Prompt** — template with `{{variable}}` support
  - **Priority** — integer, highest wins
  - **Enabled** — yes/no
- **Rule resolution**: Collect all matching enabled rules for attribute+store, sort by priority, use highest. Fall back to default prompt from Phase 1's dynamic rows if no rule matches.
- **Preview/test**: On the rule edit form, "Test" button — enter a SKU, see the resolved prompt + API response
- **Storage**: New DB tables for rules and conditions (entities, not config)
- **Phase 1 compatibility**: Dynamic row config becomes "default prompts" — rules layer on top, no migration needed

### Config Migration

- **New section**: `ai_integration` under the Services tab, matching the translation module's structure
- **Group**: "Data Enrichment" group within that section
- **Data patch**: Migrate all `catalog_ai/*` config values to the new path
- **No legacy path support** — document in release notes

---

## Phase 4: Review/Approval Workflow

**Issues:** #28

### Enrichment Review Grid

- **Admin grid**: "AI Enrichment Review" under the AI Integration section. Columns: product name/SKU, attribute, store, status, generated content (truncated), generated date. Filterable by status, store, attribute.
- **Inline editing**: Admin clicks into a row to see full content, edit, approve, or reject
- **Statuses expand**: Phase 2 statuses gain `approved` and `rejected`. Rejected clears or reverts the attribute value.
- **Original value backup**: Before enrichment overwrites, store the previous value in the log table for revert on rejection
- **Review mode config**: Toggle "Require review before publish". When enabled:
  - Enricher writes to log with `pending_review` but does NOT set on the product
  - Admin approves in grid -> content written to product and saved
- **Bulk grid actions**: Approve selected, reject selected, re-generate selected

### Content Deduplication

- **Hash tracking**: Compute hash of resolved prompt (after variable substitution), store in log alongside generated output
- **Cache hit**: Before calling OpenAI, check for existing log entry with same prompt hash + attribute + store. Reuse if found.
- **Cache invalidation**: Prompt or source data changes -> hash won't match -> fresh API call. No manual cache management.

---

## Phase 5: Structured Attribute Extraction

**Issues:** #7

### Attribute Extraction

- **New enrichment type**: "Attribute Extraction" alongside "Content Generation". Separate config/rules — prompt engineering is fundamentally different (extract/classify vs. create).
- **Attribute mapping config**: Admin defines extraction rules:
  - **Source attribute(s)** — where to read from (e.g., description)
  - **Target attribute** — attribute to fill (dropdown, multiselect, boolean, text, decimal)
  - **Extraction prompt** — instructions for the AI
  - **Value mapping** — for dropdowns/multiselects: map AI output strings to option IDs, supports fuzzy matching
  - **Conditions** — reuse Phase 3 conditions engine
- **Structured output**: Use OpenAI's JSON mode (`response_format: { type: "json_object" }`) for reliable structured responses
- **Validation**: Validate extracted value against attribute constraints before writing. Log failures.
- **Review integration**: Extracted values go through Phase 4 review flow, defaulting to `pending_review` status

---

## Decision Log

| Decision | Rationale |
|---|---|
| Keep per-attribute API calls (not unified) | Per-attribute prompts give more control, especially with the rules engine where different attributes may need completely different prompts per store/category/language |
| Pragmatic BC, no legacy config paths | Avoids carrying dead weight; data patches handle migration cleanly |
| Dynamic rows before rules engine | Dynamic rows is a quick win that unblocks configurable attributes; rules engine layers on top later |
| Config migration paired with rules engine | Both touch prompt storage/admin structure — one release reshuffles the admin experience |
| Review workflow as Phase 4 | Needs the enrichment log from Phase 2 and benefits from the rules engine in Phase 3 |
| Structured extraction last | Most experimental feature, benefits from all prior infrastructure (rules, review, configurable attributes) |
