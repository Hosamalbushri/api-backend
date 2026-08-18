<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-token',
            'X-Company-Id' => '00000000-0000-4000-8000-000000000001',
            'X-User-Id' => '00000000-0000-4000-8000-000000000002',
            'X-Device-Id' => '00000000-0000-4000-8000-0000000000a1',
        ];
    }

    private function op(
        string $entityType = 'customer',
        ?string $entityId = null,
        string $opType = 'create',
        int $baseVersion = 0,
        ?array $payload = null,
        ?string $operationId = null,
    ): array {
        $eid = $entityId ?? (string) Str::uuid();

        return [
            'operation_id' => $operationId ?? (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $eid,
            'type' => $opType,
            'base_version' => $baseVersion,
            'payload' => $payload ?? [
                'uuid' => $eid,
                'customerCode' => 'CUS-0001',
                'name' => 'Ahmed',
                'isActive' => true,
                'dataSource' => 'local',
            ],
        ];
    }

    public function test_health(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok');
    }

    public function test_unauthorized(): void
    {
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(),
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');
    }

    public function test_create_entity(): void
    {
        $op = $this->op();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $op,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('remote_version', 1)
            ->assertJsonPath('entity_id', $op['entity_id'])
            ->assertJsonPath('server_payload.name', 'Ahmed');
    }

    public function test_update_entity(): void
    {
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $this->authHeaders())->assertOk();

        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(
                entityId: $entityId,
                opType: 'update',
                baseVersion: 1,
                payload: [
                    'uuid' => $entityId,
                    'customerCode' => 'CUS-0001',
                    'name' => 'Ahmed Updated',
                    'isActive' => true,
                    'dataSource' => 'local',
                ],
            ),
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('remote_version', 2)
            ->assertJsonPath('server_payload.name', 'Ahmed Updated');
    }

    public function test_update_missing_entity_upserts(): void
    {
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'product',
            'operation' => $this->op(
                entityType: 'product',
                entityId: $entityId,
                opType: 'update',
                baseVersion: 3,
                payload: [
                    'uuid' => $entityId,
                    'itemCode' => 'P-100',
                    'name' => 'Local-only product',
                    'price' => 12.5,
                ],
            ),
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('entity_id', $entityId)
            ->assertJsonPath('remote_version', 4)
            ->assertJsonPath('server_payload.name', 'Local-only product');

        $this->getJson("/api/v1/sync/meta/product/{$entityId}", $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('version', 4);
    }

    public function test_soft_delete(): void
    {
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $this->authHeaders())->assertOk();

        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId, opType: 'delete', baseVersion: 1),
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('remote_version', 2)
            ->assertJsonPath('server_payload.deleted', true);

        $changes = $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0', $this->authHeaders())
            ->assertOk()
            ->json('changes');
        $this->assertTrue(collect($changes)->contains(
            fn ($c) => $c['entity_id'] === $entityId && $c['deleted'] === true
        ));
    }

    public function test_duplicate_operation_idempotent(): void
    {
        $op = $this->op();
        $first = $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $op,
        ], $this->authHeaders())->assertOk();
        $second = $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $op,
        ], $this->authHeaders())->assertOk();
        $this->assertSame($first->json('remote_version'), $second->json('remote_version'));

        $changes = $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0', $this->authHeaders())
            ->json('changes');
        $creates = collect($changes)->filter(
            fn ($c) => $c['entity_id'] === $op['entity_id'] && $c['operation'] === 'create'
        );
        $this->assertCount(1, $creates);
    }

    public function test_create_idempotent_when_server_already_advanced(): void
    {
        $entityId = (string) Str::uuid();
        $payload = [
            'uuid' => $entityId,
            'name' => 'Cash',
            'accountCode' => '1100',
            'isActive' => true,
        ];
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'account',
            'operation' => $this->op(entityType: 'account', entityId: $entityId, payload: $payload),
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('remote_version', 1);

        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'account',
            'operation' => $this->op(
                entityType: 'account',
                entityId: $entityId,
                opType: 'update',
                baseVersion: 1,
                payload: array_merge($payload, ['name' => 'Cash (renamed)']),
            ),
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('remote_version', 2);

        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'account',
            'operation' => $this->op(
                entityType: 'account',
                entityId: $entityId,
                opType: 'create',
                baseVersion: 1,
                payload: $payload,
            ),
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('remote_version', 2)
            ->assertJsonPath('entity_id', $entityId);
    }

    public function test_version_conflict(): void
    {
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $this->authHeaders())->assertOk();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(
                entityId: $entityId,
                opType: 'update',
                baseVersion: 1,
                payload: [
                    'uuid' => $entityId,
                    'name' => 'From A',
                    'customerCode' => 'CUS-0001',
                    'isActive' => true,
                    'dataSource' => 'local',
                ],
            ),
        ], $this->authHeaders())->assertOk();

        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(
                entityId: $entityId,
                opType: 'update',
                baseVersion: 1,
                payload: [
                    'uuid' => $entityId,
                    'name' => 'From B',
                    'customerCode' => 'CUS-0001',
                    'isActive' => true,
                    'dataSource' => 'local',
                ],
            ),
        ], $this->authHeaders())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict')
            ->assertJsonPath('error.details.server_version', 2)
            ->assertJsonPath('error.details.client_base_version', 1)
            ->assertJsonPath('error.details.server_record.name', 'From A');
    }

    public function test_pull_and_cursor(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $eid = (string) Str::uuid();
            $this->postJson('/api/v1/sync/push', [
                'entity_type' => 'customer',
                'operation' => $this->op(entityId: $eid, payload: [
                    'uuid' => $eid,
                    'name' => $name,
                    'customerCode' => 'CUS-'.$name,
                    'isActive' => true,
                    'dataSource' => 'local',
                ]),
            ], $this->authHeaders())->assertOk();
        }
        $page1 = $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0&limit=2', $this->authHeaders())
            ->assertOk()
            ->json();
        $this->assertCount(2, $page1['changes']);
        $this->assertTrue($page1['has_more']);
        $page2 = $this->getJson(
            '/api/v1/sync/pull?entity_type=customer&cursor='.$page1['next_cursor'].'&limit=10',
            $this->authHeaders()
        )->json();
        $this->assertCount(1, $page2['changes']);
        $this->assertFalse($page2['has_more']);
    }

    public function test_multiple_devices(): void
    {
        $deviceA = array_merge($this->authHeaders(), ['X-Device-Id' => '00000000-0000-4000-8000-0000000000a1']);
        $deviceB = array_merge($this->authHeaders(), ['X-Device-Id' => '00000000-0000-4000-8000-0000000000b2']);
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $deviceA)->assertOk();

        $pullB = $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0', $deviceB)->json('changes');
        $this->assertTrue(collect($pullB)->contains(
            fn ($c) => $c['entity_id'] === $entityId && $c['payload']['name'] === 'Ahmed'
        ));

        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(
                entityId: $entityId,
                opType: 'update',
                baseVersion: 1,
                payload: [
                    'uuid' => $entityId,
                    'name' => 'Ahmed From B',
                    'customerCode' => 'CUS-0001',
                    'isActive' => true,
                    'dataSource' => 'local',
                ],
            ),
        ], $deviceB)->assertOk()->assertJsonPath('remote_version', 2);

        $pullA = $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=1', $deviceA)->json('changes');
        $this->assertTrue(collect($pullA)->contains(
            fn ($c) => $c['entity_id'] === $entityId && $c['payload']['name'] === 'Ahmed From B'
        ));
    }

    public function test_batch_push_partial_conflict(): void
    {
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $this->authHeaders())->assertOk();
        $other = (string) Str::uuid();
        $results = $this->postJson('/api/v1/sync/push/batch', [
            'operations' => [
                $this->op(entityId: $other, payload: [
                    'uuid' => $other,
                    'name' => 'New',
                    'customerCode' => 'CUS-NEW',
                    'isActive' => true,
                    'dataSource' => 'local',
                ]),
                $this->op(entityId: $entityId, opType: 'update', baseVersion: 0, payload: [
                    'uuid' => $entityId,
                    'name' => 'Stale',
                    'customerCode' => 'CUS-0001',
                    'isActive' => true,
                    'dataSource' => 'local',
                ]),
            ],
        ], $this->authHeaders())->assertOk()->json('results');
        $this->assertSame('success', $results[0]['status']);
        $this->assertSame('conflict', $results[1]['status']);
    }

    public function test_failed_request_retry_same_operation_id(): void
    {
        $op = $this->op();
        $r1 = $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $op,
        ], $this->authHeaders());
        $r2 = $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $op,
        ], $this->authHeaders());
        $this->assertSame($r1->json(), $r2->json());
    }

    public function test_client_company_header_cannot_switch_tenant(): void
    {
        $forged = array_merge($this->authHeaders(), [
            'X-Company-Id' => '00000000-0000-4000-8000-0000000000aa',
        ]);
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $forged)->assertOk();

        $changes = $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0', $this->authHeaders())
            ->assertOk()
            ->json('changes');
        $this->assertTrue(collect($changes)->contains(fn ($c) => $c['entity_id'] === $entityId));
    }

    public function test_get_meta(): void
    {
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => $this->op(entityId: $entityId),
        ], $this->authHeaders())->assertOk();
        $this->getJson("/api/v1/sync/meta/customer/{$entityId}", $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('entity_id', $entityId)
            ->assertJsonPath('version', 1);
    }

    public function test_product_and_inventory_entity_types(): void
    {
        $productId = (string) Str::uuid();
        $invId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'product',
            'operation' => [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'product',
                'entity_id' => $productId,
                'type' => 'create',
                'base_version' => 0,
                'payload' => [
                    'uuid' => $productId,
                    'itemCode' => 'SKU-1',
                    'name' => 'Widget',
                    'packSize' => 12,
                    'price' => 9.5,
                ],
            ],
        ], $this->authHeaders())->assertOk();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'inventory_item',
            'operation' => [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'inventory_item',
                'entity_id' => $invId,
                'type' => 'create',
                'base_version' => 0,
                'payload' => [
                    'id' => $invId,
                    'itemCode' => 'SKU-1',
                    'itemName' => 'Widget',
                    'systemQuantity' => 10,
                    'actualQuantity' => 9,
                ],
            ],
        ], $this->authHeaders())->assertOk();
    }

    public function test_journal_entry_entity_type(): void
    {
        $entryId = (string) Str::uuid();
        $lineId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'journal_entry',
            'operation' => [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'journal_entry',
                'entity_id' => $entryId,
                'type' => 'create',
                'base_version' => 0,
                'payload' => [
                    'uuid' => $entryId,
                    'voucherNumber' => 'J-1',
                    'voucherType' => 'بيع نقدي',
                    'currencyCode' => 'SAR',
                    'isPosted' => true,
                    'sourceType' => 'sale',
                    'sourceId' => (string) Str::uuid(),
                    'lines' => [[
                        'uuid' => $lineId,
                        'accountUuid' => $accountId,
                        'accountCode' => '4100',
                        'debit' => 0,
                        'credit' => 50,
                        'currencyCode' => 'SAR',
                        'sortOrder' => 0,
                    ]],
                ],
            ],
        ], $this->authHeaders())->assertOk()->assertJsonPath('entity_id', $entryId)->assertJsonPath('remote_version', 1);

        $changes = $this->getJson('/api/v1/sync/pull?entity_type=journal_entry&cursor=0', $this->authHeaders())
            ->assertOk()
            ->json('changes');
        $this->assertTrue(collect($changes)->contains(fn ($c) => $c['entity_id'] === $entryId));
    }
}
