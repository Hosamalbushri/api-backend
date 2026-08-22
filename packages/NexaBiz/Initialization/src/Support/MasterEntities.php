<?php

namespace NexaBiz\Initialization\Support;

use NexaBiz\Synchronization\Support\SupportedEntities;

/**
 * Entity types that constitute a company's initialization snapshot.
 *
 * Kept in a tiny support class so both the service and the controller can
 * validate without depending on config loading order. Every type must also
 * be present in SupportedEntities — the sync engine owns the payload shape.
 */
final class MasterEntities
{
    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        $configured = (array) config('nexabiz.master_entity_types', [
            'company_profile',
            'account',
            'fiscal_year',
            'currency_rate',
        ]);

        return array_values(array_filter(
            $configured,
            fn (string $type): bool => SupportedEntities::isSupported($type),
        ));
    }

    public static function isMaster(string $entityType): bool
    {
        return in_array($entityType, self::types(), true);
    }
}
