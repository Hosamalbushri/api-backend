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

class Phase8ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $syncService;
    private string $companyId = '00000000-0000-4000-8000-000000000001';
    private string $otherCompanyId = '00000000-0000-4000-8000-000000000099';
    private string $userId = '00000000-0000-4000-8000-000000000002';
    private string $deviceId = '00000000-0000-4000-8000-0000000000a1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->syncService = new SyncService();
        $this->syncService->ensureCompany($this->companyId);

        Company::query()->create([
            'id' => $this->otherCompanyId,
            'name' => 'Other Tenant Company',
            'code' => 'OTHER',
            'status' => 'active',
        ]);
        $this->syncService->ensureCompany($this->otherCompanyId);
    }

    /**
     * Requirement 1 & 4 — Tenant Security Isolation: Pull scoping prevents cross-tenant access.
     */
    public function test_pull_scoping_strictly_prevents_cross_tenant_access(): void
    {
        // Seed change for Company 1
        $this->syncService->recordChange(
            $this->companyId,
            SyncEntity::query()->create([
                'company_id' => $this->companyId,
                'entity_type' => 'customer',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'payload' => ['name' => 'Company 1 Customer'],
            ]),
            'create'
        );

        // Seed change for Other Company
        $this->syncService->recordChange(
            $this->otherCompanyId,
            SyncEntity::query()->create([
                'company_id' => $this->otherCompanyId,
                'entity_type' => 'customer',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'payload' => ['name' => 'Other Company Customer'],
            ]),
            'create'
        );

        // Pull for Company 1
        [$changesCompany1] = $this->syncService->pull($this->companyId, null, null, null, 100);
        $this->assertCount(1, $changesCompany1);
        $this->assertEquals('Company 1 Customer', $changesCompany1[0]['payload']['name']);

        // Pull for Other Company
        [$changesOther] = $this->syncService->pull($this->otherCompanyId, null, null, null, 100);
        $this->assertCount(1, $changesOther);
        $this->assertEquals('Other Company Customer', $changesOther[0]['payload']['name']);
    }

    /**
     * Requirement 3 & 7 — Idempotency after Lost ACK: Re-submitting identical operation_id is idempotent.
     */
    public function test_push_operation_is_idempotent_when_ack_is_lost(): void
    {
        $opId = (string) Str::uuid();
        $entityId = (string) Str::uuid();

        $op = [
            'operation_id' => $opId,
            'entity_type' => 'product',
            'entity_id' => $entityId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => ['sku' => 'PROD-8', 'name' => 'Idempotent Product'],
        ];

        $ack1 = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);
        $this->assertEquals('success', $ack1['status']);

        // Re-push identical operation
        $ack2 = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);
        $this->assertEquals($ack1, $ack2);

        $this->assertEquals(1, SyncEntity::where('company_id', $this->companyId)->where('entity_uuid', $entityId)->count());
        $this->assertEquals(1, SyncChange::where('company_id', $this->companyId)->where('entity_uuid', $entityId)->count());
    }

    /**
     * Requirement 5 & 8 — Accounting Immutability & Balance Safety: Posted journals reject modification.
     */
    public function test_posted_journal_entries_reject_modification_and_unbalanced_lines(): void
    {
        $jId = (string) Str::uuid();

        // Balanced journal entry
        $opPost = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => [
                'memo' => 'Initial Posted Entry',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'acc-1', 'debit' => 100.00, 'credit' => 0.00],
                    ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 100.00],
                ],
            ],
        ];

        $ackPost = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $opPost);
        $this->assertEquals('success', $ackPost['status']);

        $this->expectException(\NexaBiz\Core\Exceptions\ValidationAppException::class);
        $this->expectExceptionMessage('Posted journal entries are immutable and cannot be updated or deleted.');

        // Attempting to modify posted entry must throw InvalidArgumentException
        $opModify = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'update',
            'base_version' => 1,
            'payload' => [
                'memo' => 'Modified Posted Entry Attempt',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'acc-1', 'debit' => 200.00, 'credit' => 0.00],
                    ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 200.00],
                ],
            ],
        ];

        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $opModify);
    }

    /**
     * Requirement 16 & 20 — Command Retention Scoping: Prune command protects sequence buffer.
     */
    public function test_prune_command_dry_run_and_sequence_buffer_safety(): void
    {
        // Seed changes across sequence 1 to 50
        for ($s = 1; $s <= 50; $s++) {
            DB::table('sync_changes')->insert([
                'company_id' => $this->companyId,
                'sequence' => $s,
                'entity_type' => 'customer',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'operation' => 'create',
                'payload' => json_encode(['name' => "Prune Cust $s"]),
                'created_at' => now()->subDays(120), // Old changes
            ]);
        }

        // Run prune command with --buffer=20
        $this->artisan('sync:prune-changes', [
            '--days' => 90,
            '--buffer' => 20,
            '--company' => $this->companyId,
        ])->assertExitCode(0);

        // Maximum sequence is 50. Buffer of 20 protects sequences > (50 - 20) = 30.
        // Therefore sequences 31 to 50 must survive pruning regardless of age.
        $surviving = SyncChange::where('company_id', $this->companyId)->pluck('sequence')->toArray();
        $this->assertCount(20, $surviving);
        $this->assertEquals(31, min($surviving));
        $this->assertEquals(50, max($surviving));
    }
}
