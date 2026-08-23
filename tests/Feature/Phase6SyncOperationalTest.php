<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Synchronization\Services\SyncService;
use Tests\TestCase;

class Phase6SyncOperationalTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $syncService;
    private string $company1 = '00000000-0000-4000-8000-000000000001';
    private string $company2 = '00000000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        
        Company::query()->create([
            'id' => $this->company1,
            'name' => 'Company 1',
            'code' => 'C1',
            'status' => 'active',
        ]);

        Company::query()->create([
            'id' => $this->company2,
            'name' => 'Company 2',
            'code' => 'C2',
            'status' => 'active',
        ]);

        $this->syncService = new SyncService();
        $this->syncService->ensureCompany($this->company1);
        $this->syncService->ensureCompany($this->company2);
    }

    public function test_prune_changes_dry_run_mode_does_not_delete_records(): void
    {
        // Insert old change log for company 1
        DB::table('sync_changes')->insert([
            'company_id' => $this->company1,
            'sequence' => 10,
            'entity_type' => 'customer',
            'entity_uuid' => (string) Str::uuid(),
            'version' => 1,
            'operation' => 'create',
            'payload' => json_encode(['name' => 'Old Customer']),
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('sync:prune-changes', [
            '--days' => 90,
            '--dry-run' => true,
        ])->assertExitCode(0);

        // Record must still exist
        $this->assertEquals(1, DB::table('sync_changes')->where('company_id', $this->company1)->count());
    }

    public function test_prune_changes_deletes_records_older_than_cutoff_while_preserving_buffer(): void
    {
        // Insert 5 old records (seq 1 to 5) and 5 newer records (seq 6 to 10)
        for ($i = 1; $i <= 10; $i++) {
            DB::table('sync_changes')->insert([
                'company_id' => $this->company1,
                'sequence' => $i,
                'entity_type' => 'customer',
                'entity_uuid' => (string) Str::uuid(),
                'version' => 1,
                'operation' => 'create',
                'payload' => json_encode(['name' => "Cust $i"]),
                'created_at' => $i <= 5 ? now()->subDays(100) : now(),
            ]);
        }

        // Run pruning with buffer=3 (max sequence is 10, safe max prune seq is 7)
        $this->artisan('sync:prune-changes', [
            '--days' => 90,
            '--buffer' => 3,
        ])->assertExitCode(0);

        // Sequences 1 to 5 were created 100 days ago and <= 7, so all 5 are pruned
        $remaining = DB::table('sync_changes')->where('company_id', $this->company1)->pluck('sequence')->toArray();
        $this->assertEquals([6, 7, 8, 9, 10], $remaining);
    }

    public function test_prune_changes_respects_company_scoping(): void
    {
        DB::table('sync_changes')->insert([
            'company_id' => $this->company1,
            'sequence' => 1,
            'entity_type' => 'customer',
            'entity_uuid' => (string) Str::uuid(),
            'version' => 1,
            'operation' => 'create',
            'payload' => json_encode(['name' => 'Comp1 Customer']),
            'created_at' => now()->subDays(100),
        ]);

        DB::table('sync_changes')->insert([
            'company_id' => $this->company2,
            'sequence' => 1,
            'entity_type' => 'customer',
            'entity_uuid' => (string) Str::uuid(),
            'version' => 1,
            'operation' => 'create',
            'payload' => json_encode(['name' => 'Comp2 Customer']),
            'created_at' => now()->subDays(100),
        ]);

        // Prune only company 1
        $this->artisan('sync:prune-changes', [
            '--company' => $this->company1,
            '--days' => 90,
            '--buffer' => 0,
        ])->assertExitCode(0);

        $this->assertEquals(0, DB::table('sync_changes')->where('company_id', $this->company1)->count());
        $this->assertEquals(1, DB::table('sync_changes')->where('company_id', $this->company2)->count());
    }

    public function test_tenant_security_isolation_prevents_cross_tenant_access(): void
    {
        // Seed entity for Company 1
        DB::table('sync_entities')->insert([
            'company_id' => $this->company1,
            'entity_type' => 'customer',
            'entity_uuid' => '11111111-1111-4000-8000-111111111111',
            'version' => 1,
            'payload' => json_encode(['name' => 'Tenant 1 Secret']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Direct query check: company 2 query for company 1 entity returns null
        $entityCompany2 = $this->syncService->getMeta(
            companyId: $this->company2,
            entityType: 'customer',
            entityId: '11111111-1111-4000-8000-111111111111',
        );

        // Must return null for non-existent entity under company 2 context
        $this->assertNull($entityCompany2);
    }
}
