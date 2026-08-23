<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Synchronization\Models\SyncChange;
use NexaBiz\Synchronization\Models\SyncEntity;
use NexaBiz\Synchronization\Services\SyncService;
use Tests\TestCase;

class Phase7SyncScalabilityTest extends TestCase
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

    /**
     * Audit Requirement 2 & 3 — sync_changes Scale & Large Pull Backlog Pagination.
     */
    public function test_pull_pagination_scale_across_1000_changes(): void
    {
        // Seed 1,000 changes into sync_changes
        $records = [];
        for ($i = 1; $i <= 1000; $i++) {
            $records[] = [
                'company_id' => $this->companyId,
                'sequence' => $i,
                'entity_type' => 'customer',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'operation' => 'create',
                'payload' => json_encode(['name' => "Scale Customer $i"]),
                'created_at' => now(),
            ];
        }

        // Insert in chunks of 200 for speed
        foreach (array_chunk($records, 200) as $chunk) {
            DB::table('sync_changes')->insert($chunk);
        }

        $cursor = null;
        $totalFetched = 0;
        $pages = 0;

        $startTime = microtime(true);

        do {
            [$changes, $nextCursor, $hasMore] = $this->syncService->pull(
                companyId: $this->companyId,
                entityType: null,
                cursor: $cursor,
                since: null,
                limit: 100, // Page limit
            );

            $fetchedCount = count($changes);
            $totalFetched += $fetchedCount;
            $pages++;
            $cursor = $nextCursor;

            $this->assertLessThanOrEqual(100, $fetchedCount, 'Page limit must not exceed 100');
        } while ($hasMore);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->assertEquals(1000, $totalFetched, 'Must pull exactly 1,000 changes across pages');
        $this->assertEquals(10, $pages, 'Must page cleanly in 10 pages of 100');
        $this->assertEquals(1000, $cursor, 'Final cursor must be 1000');
        $this->assertLessThan(2000, $durationMs, 'Pulling 1,000 changes in 10 pages must complete under 2 seconds');
    }

    /**
     * Audit Requirement 6 & 8 — Multi-Tenant Monotonic Sequence Isolation.
     */
    public function test_multi_tenant_sequence_isolation_across_10_companies(): void
    {
        $companies = [];
        for ($c = 1; $c <= 10; $c++) {
            $cid = (string) Str::uuid();
            Company::query()->create([
                'id' => $cid,
                'name' => "Tenant Company $c",
                'code' => "TC$c",
                'status' => 'active',
            ]);
            $this->syncService->ensureCompany($cid);
            $companies[] = $cid;
        }

        // Allocate sequence for each tenant concurrently in loop
        foreach ($companies as $idx => $cid) {
            $seq1 = $this->syncService->nextSequence($cid);
            $seq2 = $this->syncService->nextSequence($cid);

            $this->assertEquals(1, $seq1, "Company $idx first sequence must start at 1");
            $this->assertEquals(2, $seq2, "Company $idx second sequence must be 2");
        }

        // Ensure Tenant 1 sequence is completely unaffected by Tenant 2 allocations
        $this->assertEquals(3, $this->syncService->nextSequence($companies[0]));
    }

    /**
     * Audit Requirement 7 — Idempotency Lookup Overhead under Repeat Submissions.
     */
    public function test_idempotency_lookup_performance_under_repeat_submissions(): void
    {
        $opId = (string) Str::uuid();
        $entityId = (string) Str::uuid();

        $op = [
            'operation_id' => $opId,
            'entity_type' => 'product',
            'entity_id' => $entityId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => ['sku' => 'IDEM-1', 'name' => 'Idempotency Product'],
        ];

        // Initial push
        $ack1 = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);

        $startTime = microtime(true);

        // Repeat submit 50 times
        for ($r = 0; $r < 50; $r++) {
            $ackRepeat = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);
            $this->assertEquals($ack1, $ackRepeat);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        // Verify counts remain unchanged
        $this->assertEquals(1, SyncEntity::where('company_id', $this->companyId)->where('entity_uuid', $entityId)->count());
        $this->assertEquals(1, SyncChange::where('company_id', $this->companyId)->where('entity_uuid', $entityId)->count());
        $this->assertLessThan(1500, $durationMs, '50 repeated idempotency lookups must execute under 1.5 seconds');
    }

    /**
     * Audit Requirement 11 — Large Payload Test (50-line journal entry).
     */
    public function test_large_payload_journal_entry_processing(): void
    {
        $lines = [];
        for ($l = 1; $l <= 25; $l++) {
            $lines[] = ['account_id' => "acc-debit-$l", 'debit' => 10.00, 'credit' => 0.00];
            $lines[] = ['account_id' => "acc-credit-$l", 'debit' => 0.00, 'credit' => 10.00];
        }

        $jId = (string) Str::uuid();
        $op = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => [
                'memo' => 'Large 50-line Balanced Journal Entry Benchmark',
                'isPosted' => false,
                'lines' => $lines,
            ],
        ];

        $startTime = microtime(true);
        $ack = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->assertEquals('success', $ack['status']);
        $this->assertEquals($jId, $ack['entity_id']);
        $this->assertLessThan(500, $durationMs, '50-line journal entry push must complete under 500 ms');
    }
}
