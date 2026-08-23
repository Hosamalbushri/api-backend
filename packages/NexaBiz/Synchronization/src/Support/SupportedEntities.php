<?php

namespace NexaBiz\Synchronization\Support;

final class SupportedEntities
{
    public const TYPES = [
        'product',
        'inventory_item',
        'inventory_movement',
        'customer',
        'supplier',
        'account',
        'journal_entry',
        'fiscal_year',
        'currency_rate',
        'sale',
        'purchase',
        'financial_transaction',
        'company_profile',
        'fund_transfer',
        'currency_conversion',
    ];

    public static function isSupported(string $entityType): bool
    {
        return in_array($entityType, self::TYPES, true);
    }

    public static function sorted(): array
    {
        $types = self::TYPES;
        sort($types);

        return $types;
    }
}
