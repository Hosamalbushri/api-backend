<?php

namespace Tests\Unit;

use NexaBiz\Identity\Support\PermissionsCatalog;
use Tests\TestCase;

class PermissionsCatalogTest extends TestCase
{
    public function test_expand_aliases_are_bidirectional(): void
    {
        $expanded = PermissionsCatalog::expandPermissionCodes(['products.create']);
        $this->assertContains('products.create', $expanded);
        $this->assertContains('inventory.products.create', $expanded);
    }

    public function test_sync_entity_permissions_cover_flutter_types(): void
    {
        $map = PermissionsCatalog::syncEntityPermissions();
        foreach (['product', 'inventory_item', 'customer', 'account', 'sale', 'journal_entry'] as $type) {
            $this->assertArrayHasKey($type.'|create', $map);
            $this->assertArrayHasKey($type.'|update', $map);
            $this->assertArrayHasKey($type.'|delete', $map);
        }
    }
}
