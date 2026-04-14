<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api\Data;

interface ExtractionRuleInterface
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const SOURCE_ATTRIBUTES = 'source_attributes';
    public const TARGET_ATTRIBUTE = 'target_attribute';
    public const EXTRACTION_PROMPT = 'extraction_prompt';
    public const VALUE_MAPPING = 'value_mapping';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const STORE_IDS = 'store_ids';
    public const PRIORITY = 'priority';
    public const IS_ACTIVE = 'is_active';
}
