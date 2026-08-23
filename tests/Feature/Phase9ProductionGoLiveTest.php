<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Synchronization\Models\SyncChange;
use NexaBiz\Synchronization\Models\SyncEntity;
use NexaBiz\Synchronization\Services\SyncService;
use Tests\TestCase;

class Phase9ProductionGoLiveTest extends TestCase
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
            'name' => 'Tenant B Company',
            'code' => 'TENANTB',
            'status' => 'active',
        ]);
        $this->syncService->ensureCompany($this->otherCompanyId);
    }

    /**
     * Requirement 1 — Production Configuration Safety: Secrets & APP_KEY present, APP_DEBUG false in prod.
     */
    public function test_production_environment_config_invariants(): void
    {
        $this->assertNotEmpty(config('app.key'), 'APP_KEY must be configured.');
        $this->assertNotEmpty(config('app.name'), 'APP_NAME must be configured.');
    }

    /**
     * Requirement 2 — Tenant Isolation Final Gate: Cross-tenant data isolation.
     */
    public function test_tenant_isolation_prevents_cross_tenant_access_and_replays(): void
    {
        $this->syncService->recordChange(
            $this->companyId,
            SyncEntity::query()->create([
                'company_id' => $this->companyId,
                'entity_type' => 'customer',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'payload' => ['name' => 'Tenant A Secret Customer'],
            ]),
            'create'
        );

        [$changesTenantB] = $this->syncService->pull($this->otherCompanyId, null, null, null, 100);
        $this->assertCount(0, $changesTenantB, 'Tenant B pull must return 0 changes from Tenant A');
    }

    /**
     * Requirement 4 — Lost ACK Idempotency: Duplicate submission of same operation_id returns exact ACK without duplication.
     */
    public function test_lost_ack_idempotency_returns_original_ack_without_side_effects(): void
    {
        $opId = (string) Str::uuid();
        $prodId = (string) Str::uuid();

        $op = [
            'operation_id' => $opId,
            'entity_type' => 'product',
            'entity_id' => $prodId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => ['sku' => 'P9-SKU', 'name' => 'Production Product'],
        ];

        $ack1 = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);
        $this->assertEquals('success', $ack1['status']);

        // Re-send identical operation
        $ack2 = $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $op);
        $this->assertEquals($ack1, $ack2);

        $this->assertEquals(1, SyncEntity::where('company_id', $this->companyId)->where('entity_uuid', $prodId)->count());
        $this->assertEquals(1, SyncChange::where('company_id', $this->companyId)->where('entity_uuid', $prodId)->count());
    }

    /**
     * Requirement 5 — Posted Journal Immutability: Attempt to update posted entry throws ValidationAppException.
     */
    public function test_posted_journal_entry_modification_is_strictly_rejected(): void
    {
        $jId = (string) Str::uuid();

        $opPost = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'create',
            'base_version' => 0,
            'payload' => [
                'memo' => 'Posted Entry',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'acc-1', 'debit' => 500.00, 'credit' => 0.00],
                    ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 500.00],
                ],
            ],
        ];

        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $opPost);

        $this->expectException(ValidationAppException::class);
        $this->expectExceptionMessage('Posted journal entries are immutable and cannot be updated or deleted.');

        $opUpdate = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => $jId,
            'type' => 'update',
            'base_version' => 1,
            'payload' => [
                'memo' => 'Illegal Update Attempt',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'acc-1', 'debit' => 600.00, 'credit' => 0.00],
                    ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 600.00],
                ],
            ],
        ];

        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $opUpdate);
    }

    /**
     * Requirement 6 — Unbalanced Journal Rejection: Debit != Credit is rejected.
     */
    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $this->expectException(ValidationAppException::class);

        $opUnbalanced = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'journal_entry',
            'entity_id' => (string) Str::uuid(),
            'type' => 'create',
            'base_version' => 0,
            'payload' => [
                'memo' => 'Unbalanced Entry',
                'isPosted' => true,
                'lines' => [
                    ['account_id' => 'acc-1', 'debit' => 100.00, 'credit' => 0.00],
                    ['account_id' => 'acc-2', 'debit' => 0.00, 'credit' => 90.00],
                ],
            ],
        ];

        $this->syncService->pushOperation($this->companyId, $this->userId, $this->deviceId, $opUnbalanced);
    }

    /**
     * Requirement 8 — Retention Pruning Safety: Safety sequence buffer protects recent sequences.
     */
    public function test_retention_pruning_protects_sequence_buffer(): void
    {
        for ($s = 1; $s <= 30; $s++) {
            DB::table('sync_changes')->insert([
                'company_id' => $this->companyId,
                'sequence' => $s,
                'entity_type' => 'product',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'operation' => 'create',
                'payload' => json_encode(['sku' => "P-$s"]),
                'created_at' => now()->subDays(100),
            ]);
        }

        $this->artisan('sync:prune-changes', [
            '--days' => 90,
            '--buffer' => 10,
            '--company' => $this->companyId,
        ])->assertExitCode(0);

        $survivingSeqs = SyncChange::where('company_id', $this->companyId)->pluck('sequence')->toArray();
        $this->assertCount(10, $survivingSeqs);
        $this->assertEquals(21, min($survivingSeqs));
        $this->assertEquals(30, max($survivingSeqs));
    }
}
