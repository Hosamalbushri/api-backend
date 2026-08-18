<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use NexaBiz\Identity\Events\CompanyProvisioned;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Synchronization\Models\SyncSequence;
use Tests\TestCase;

class CompanyProvisionedTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_provisioned_creates_sync_sequence_idempotently(): void
    {
        $this->seed(IdentitySeeder::class);
        $company = Company::query()->create([
            'name' => 'Bound Co',
            'code' => 'BOUND-CO',
            'status' => 'active',
        ]);

        event(new CompanyProvisioned((string) $company->id));
        event(new CompanyProvisioned((string) $company->id));

        $this->assertSame(1, SyncSequence::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, (int) SyncSequence::query()->find($company->id)->next_value);
    }
}
