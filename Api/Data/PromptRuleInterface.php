<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api\Data;

interface PromptRuleInterface
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const ATTRIBUTE_CODE = 'attribute_code';
    public const STORE_IDS = 'store_ids';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const PROMPT = 'prompt';
    public const PRIORITY = 'priority';
    public const IS_ACTIVE = 'is_active';

    public function getRuleId(): ?int;
    public function getName(): string;
    public function getAttributeCode(): string;
    public function getStoreIds(): string;
    public function getConditionsSerialized(): ?string;
    public function getPrompt(): string;
    public function getPriority(): int;
    public function getIsActive(): bool;
}
