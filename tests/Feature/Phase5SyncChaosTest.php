<?php

namespace Tests\Feature;

use NexaBiz\Core\Exceptions\ConflictException;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use NexaBiz\Synchronization\Models\SyncChange;
use NexaBiz\Synchronization\Models\SyncEntity;
use NexaBiz\Synchronization\Models\SyncOperation;
use NexaBiz\Synchronization\Models\SyncSequence;
use NexaBiz\Synchronization\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5SyncChaosTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $syncService;
    private string $companyId = '00000000-0000-4000-8000-000000000001';
    private string $userId = '00000000-0000-4000-8000-000000000002';
    private string $deviceId = '00000000-0000-4000-8000-0000000000a1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IdentitySeeder::class);
        $this->syncService = new SyncService();
        $this->syncService->ensureCompany($this->companyId);
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-token',
            'X-Company-Id' => $this->companyId,
            'X-User-Id' => $this->userId,
            'X-Device-Id' => $this->deviceId,
        ];
    }

    /**
     * PART 2 & PART 4 — Lost ACK Idempotency Test across all 8 core entities.
     */
    public function test_lost_ack_idempotency_across_all_8_entities(): void
    {
        $entities = [
            'account' => ['code' => '1010', 'name' => 'Cash'],
            'product' => ['sku' => 'PROD-1', 'name' => 'Widget A'],
            'customer' => ['name' => 'Acme Corp'],
            'supplier' => ['name' => 'Global Logistics'],
            'sale' => ['invoice_number' => 'INV-001', 'total' => 500.00],
            'financial_transaction' => ['amount' => 150.00, 'reference' => 'PAY-001'],
            'inventory_movement' => ['quantity' => 10, 'type' => 'in'],
            'journal_entry' => [
                'memo' => 'Opening balance',
                'lines' => [
                    ['account_id' => 'acc-1', 'debit' => 100.00, 'credit' => 0.00],
                    ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 100.00],
                ],
            ],
        ];

        foreach ($entities as $type => $payload) {
            $opId = (string) Str::uuid();
            $entityId = (string) Str::uuid();

            $op = [
                'operation_id' => $opId,
                'entity_type' => $type,
                'entity_id' => $entityId,
                'type' => 'create',
                'base_version' => 0,
                'payload' => $payload,
            ];

            // First Push (Server commits, sends ACK, network drops response)
            $ack1 = $this->syncService->pushOperation(
                $this->companyId,
                $this->userId,
                $this->deviceId,
                $op
            );

            // Record DB counts after initial commit
            $entityCount1 = SyncEntity::where('company_id', $this->companyId)->where('entity_type', $type)->count();
            $changeCount1 = SyncChange::where('company_id', $this->companyId)->where('entity_type', $type)->count();
            $opCount1 = SyncOperation::where('company_id', $this->companyId)->where('operation_id', $opId)->count();
            $entityVer1 = SyncEntity::where('company_id', $this->companyId)
                ->where('entity_type', $type)
                ->where('entity_uuid', $entityId)
                ->first()->version;

            // Second Push (Client retries identical operation_id after network timeout)
            $ack2 = $this->syncService->pushOperation(
                $this->companyId,
                $this->userId,
                $this->deviceId,
                $op
            );

            // Record DB counts after retry
            $entityCount2 = SyncEntity::where('company_id', $this->companyId)->where('entity_type', $type)->count();
            $changeCount2 = SyncChange::where('company_id', $this->companyId)->where('entity_type', $type)->count();
            $opCount2 = SyncOperation::where('company_id', $this->companyId)->where('operation_id', $opId)->count();
            $entityVer2 = SyncEntity::where('company_id', $this->companyId)
                ->where('entity_type', $type)
                ->where('entity_uuid', $entityId)
                ->first()->version;

            // Assert Invariants
            $this->assertEquals($ack1, $ack2, "ACK must be identical on retry for entity {$type}");
            $this->assertEquals($entityCount1, $entityCount2, "Entity count must not increase on retry for entity {$type}");
            $this->assertEquals($changeCount1, $changeCount2, "sync_changes must not receive duplicate row on retry for entity {$type}");
            $this->assertEquals(1, $opCount2, "sync_operations must have exactly 1 record for {$opId}");
            $this->assertEquals($entityVer1, $entityVer2, "Version must not increment on idempotent retry for entity {$type}");
        }
    }

    /**
     * PART 7 — Partial Push Batch Atomicity & Independent Operation Processing.
     */
    public function test_batch_push_per_operation_atomicity_and_partial_handling(): void
    {
        $cConflictId = (string) Str::uuid();
        $opBatchSub1 = (string) Str::uuid();
        $cBatch2Id = (string) Str::uuid();
        $opBatchSub2 = (string) Str::uuid();
        $opBatchSub3 = (string) Str::uuid();
        $jUnbalancedId = (string) Str::uuid();
        $opBatchSub4 = (string) Str::uuid();
        $pBatch2Id = (string) Str::uuid();

        // 1. Create a customer on server for conflict test
        $existingCustomerOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'customer',
            'entity_id' => $cConflictId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => ['name' => 'Original Customer'],
        ];
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $existingCustomerOp);

        // Update server entity to version 2
        $updateCustomerOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'customer',
            'entity_id' => $cConflictId,
            'type' => 'update',
            'base_version' => 1,
            'payload' => ['name' => 'Server Updated Customer'],
        ];
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $updateCustomerOp);

        // Batch containing:
        // Op 1: SUCCESS (Create customer C-2)
        // Op 2: CONFLICT (Update C-1 with stale base_version=1 when server is at version 2)
        // Op 3: VALIDATION ERROR (Unbalanced journal entry)
        // Op 4: SUCCESS (Create product P-2)
        $batchPayload = [
            'operations' => [
                [
                    'operation_id' => $opBatchSub1,
                    'entity_type' => 'customer',
                    'entity_id' => $cBatch2Id,
                    'type' => 'create',
                    'base_version' => 0,
                    'payload' => ['name' => 'Batch Customer 2'],
                ],
                [
                    'operation_id' => $opBatchSub2,
                    'entity_type' => 'customer',
                    'entity_id' => $cConflictId,
                    'type' => 'update',
                    'base_version' => 1, // Stale! Server is version 2
                    'payload' => ['name' => 'Stale Client Customer'],
                ],
                [
                    'operation_id' => $opBatchSub3,
                    'entity_type' => 'journal_entry',
                    'entity_id' => $jUnbalancedId,
                    'type' => 'create',
                    'base_version' => 0,
                    'payload' => [
                        'memo' => 'Unbalanced',
                        'lines' => [
                            ['account_id' => 'acc-1', 'debit' => 100.00, 'credit' => 0.00],
                            ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 50.00], // Debit != Credit!
                        ],
                    ],
                ],
                [
                    'operation_id' => $opBatchSub4,
                    'entity_type' => 'product',
                    'entity_id' => $pBatch2Id,
                    'type' => 'create',
                    'base_version' => 0,
                    'payload' => ['sku' => 'P2', 'name' => 'Batch Product 2'],
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/sync/push/batch', $batchPayload);
        $response->assertStatus(200);

        $results = $response->json('results');
        $this->assertCount(4, $results);

        // Op 1: Success
        $this->assertEquals($opBatchSub1, $results[0]['operation_id']);
        $this->assertEquals('success', $results[0]['status']);

        // Op 2: Conflict
        $this->assertEquals($opBatchSub2, $results[1]['operation_id']);
        $this->assertEquals('conflict', $results[1]['status']);
        $this->assertEquals(2, $results[1]['conflict']['server_version']);

        // Op 3: Error (Validation)
        $this->assertEquals($opBatchSub3, $results[2]['operation_id']);
        $this->assertEquals('error', $results[2]['status']);
        $this->assertEquals('validation_error', $results[2]['error']['code']);

        // Op 4: Success
        $this->assertEquals($opBatchSub4, $results[3]['operation_id']);
        $this->assertEquals('success', $results[3]['status']);

        // Verify Database State: Op 1 and Op 4 committed; Op 2 returned conflict payload; Op 3 returned validation error payload
        $this->assertNotNull(SyncEntity::where('company_id', $this->companyId)->where('entity_uuid', $cBatch2Id)->first());
        $this->assertNotNull(SyncEntity::where('company_id', $this->companyId)->where('entity_uuid', $pBatch2Id)->first());
        $this->assertEquals('conflict', $results[1]['status']);
        $this->assertEquals(2, $results[1]['conflict']['server_version']);
    }

    /**
     * PART 10 — Accounting Integrity (Balanced debit == credit & posted journal entry immutability).
     */
    public function test_accounting_integrity_unbalanced_rejection_and_posted_immutability(): void
    {
        // 1. Unbalanced Journal Entry Rejection
        $unbalancedOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => (string) Str::uuid(),
            'type' => 'create',
            'base_version' => 0,
            'payload' => [
                'memo' => 'Invalid Journal',
                'lines' => [
                    ['account_id' => 'a1', 'debit' => 100.00, 'credit' => 0.00],
                    ['account_id' => 'a2', 'debit' => 0.00, 'credit' => 80.00],
                ],
            ],
        ];

        $this->expectException(ValidationAppException::class);
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $unbalancedOp);
    }

    public function test_posted_journal_entry_immutability_enforcement(): void
    {
        $jId = (string) Str::uuid();

        // 1. Create a posted journal entry on server
        $postedJournalOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => [
                'memo' => 'Posted Entry',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'a1', 'debit' => 100.00, 'credit' => 0.00],
                    ['account_id' => 'a2', 'debit' => 0.00, 'credit' => 100.00],
                ],
            ],
        ];
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $postedJournalOp);

        // 2. Attempt update on posted journal entry
        $updateOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'update',
            'base_version' => 1,
            'payload' => [
                'memo' => 'Illegal Update',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'a1', 'debit' => 200.00, 'credit' => 0.00],
                    ['account_id' => 'a2', 'debit' => 0.00, 'credit' => 200.00],
                ],
            ],
        ];

        $this->expectException(ValidationAppException::class);
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $updateOp);
    }

    /**
     * PART 15 — Concurrency-Safe Monotonic Sequence Allocation.
     */
    public function test_monotonic_concurrency_safe_sequence_allocation(): void
    {
        $seq1 = $this->syncService->nextSequence($this->companyId);
        $seq2 = $this->syncService->nextSequence($this->companyId);
        $seq3 = $this->syncService->nextSequence($this->companyId);

        $this->assertGreaterThan(0, $seq1);
        $this->assertEquals($seq1 + 1, $seq2);
        $this->assertEquals($seq2 + 1, $seq3);

        $seqRow = SyncSequence::find($this->companyId);
        $this->assertGreaterThan($seq3, $seqRow->next_value);
    }

    /**
     * PART 16 — Tombstone Non-Resurrection for Deleted Entities.
     */
    public function test_tombstone_prevents_stale_edit_resurrection(): void
    {
        $cId = (string) Str::uuid();

        // 1. Create entity
        $createOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'customer',
            'entity_id' => $cId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => ['name' => 'Active Customer'],
        ];
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $createOp);

        // 2. Delete entity on server (Version 2)
        $deleteOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'customer',
            'entity_id' => $cId,
            'type' => 'delete',
            'base_version' => 1,
            'payload' => [],
        ];
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $deleteOp);

        // 3. Stale client attempts edit based on version 1
        $staleEditOp = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'customer',
            'entity_id' => $cId,
            'type' => 'update',
            'base_version' => 1, // Stale! Server is version 2 (deleted)
            'payload' => ['name' => 'Resurrected Customer'],
        ];

        $this->expectException(ConflictException::class);
        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $staleEditOp);
    }
}
