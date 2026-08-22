<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Identity\Models\CompanyUser;
use NexaBiz\Identity\Models\Role;
use NexaBiz\Identity\Models\User;
use Tests\TestCase;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use NexaBiz\Initialization\Database\Seeders\InitializationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    private function login(string $email = 'ahmed@example.com', string $password = 'AhmedSales!123'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $response->assertOk();

        return (string) $this->decode($response)['data']['access_token'];
    }

    private function decode($response): array
    {
        return $response->json();
    }

    private function seedEntity(string $companyId, string $type, int $index, ?string $uuid = null): void
    {
        \NexaBiz\Synchronization\Models\SyncEntity::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'entity_type' => $type,
            'entity_uuid' => $uuid ?? (string) Str::uuid(),
            'version' => 1,
            'payload' => ['name' => 'Entity '.$type.' '.$index],
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/bootstrap')->assertStatus(401);
        $this->getJson('/api/v1/bootstrap/data?entity_type=account&taken_at=2026-01-01T00:00:00Z')
            ->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->getJson('/api/v1/bootstrap', [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertStatus(401);
    }

    public function test_non_initialized_company_reports_initialized_false(): void
    {
        $token = $this->login();

        $response = $this->getJson('/api/v1/bootstrap', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.initialized', false);
        $response->assertJsonPath('data.counts.account', 0);
        $response->assertJsonPath('data.server.api_version', 'v1');
        $this->assertArrayHasKey('snapshot', $this->decode($response)['data']);
    }

    public function test_initialization_seeder_provisions_master_data(): void
    {
        $this->seed(InitializationSeeder::class);

        $token = $this->login();

        $status = $this->decode(
            $this->getJson('/api/v1/bootstrap', [
                'Authorization' => 'Bearer '.$token,
            ])->assertOk()
        )['data'];

        $this->assertTrue($status['initialized']);
        $this->assertSame(7, $status['counts']['account']);
        $this->assertSame(1, $status['counts']['fiscal_year']);
        $this->assertSame(1, $status['counts']['currency_rate']);

        // Idempotent: re-running the seeder must not duplicate rows.
        $this->seed(InitializationSeeder::class);
        $again = $this->decode(
            $this->getJson('/api/v1/bootstrap', [
                'Authorization' => 'Bearer '.$token,
            ])->assertOk()
        )['data'];
        $this->assertSame(7, $again['counts']['account']);

        // First accounts page carries the client-apply payload contract.
        $page = $this->decode(
            $this->getJson('/api/v1/bootstrap/data?'.http_build_query([
                'entity_type' => 'account',
                'taken_at' => $status['snapshot']['taken_at'],
            ]), [
                'Authorization' => 'Bearer '.$token,
            ])->assertOk()
        )['data'];
        $this->assertNotEmpty($page['items']);
        foreach ($page['items'] as $item) {
            $this->assertArrayHasKey('accountCode', $item['payload']);
            $this->assertArrayHasKey('name', $item['payload']);
            $this->assertArrayHasKey('accountType', $item['payload']);
            $this->assertArrayHasKey('normalBalance', $item['payload']);
        }
    }

    public function test_initialized_company_reports_counts_and_company_name(): void
    {
        $companyId = config('nexabiz.seed_company_id');
        $this->seedEntity($companyId, 'account', 1);
        $this->seedEntity($companyId, 'account', 2);
        $this->seedEntity($companyId, 'fiscal_year', 1);

        $token = $this->login();

        $response = $this->getJson('/api/v1/bootstrap', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.initialized', true);
        $response->assertJsonPath('data.counts.account', 2);
        $response->assertJsonPath('data.counts.fiscal_year', 1);
        $response->assertJsonPath('data.counts.currency_rate', 0);
        $response->assertJsonPath('data.company.id', (string) $companyId);
        $companyName = $this->decode($response)['data']['company']['name'];
        $this->assertTrue($companyName === 'Demo Company A' || $companyName === 'شركة النماء (الريال اليمني)');
    }

    public function test_data_endpoint_returns_paginated_items_with_keyset_cursor(): void
    {
        $companyId = config('nexabiz.seed_company_id');
        for ($i = 0; $i < 5; $i++) {
            $this->seedEntity($companyId, 'account', $i);
        }
        $token = $this->login();

        $status = $this->decode(
            $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer '.$token])
        )['data'];

        // Page 1 — small limit forces pagination.
        $page1 = $this->decode($this->getJson(
            '/api/v1/bootstrap/data?entity_type=account&limit=2&taken_at='.urlencode($status['snapshot']['taken_at']),
            ['Authorization' => 'Bearer '.$token],
        ))['data'];

        $this->assertCount(2, $page1['items']);
        $this->assertTrue($page1['has_more']);
        $this->assertNotNull($page1['next_cursor']);

        // Page 2 continues after the cursor.
        $page2 = $this->decode($this->getJson(
            '/api/v1/bootstrap/data?entity_type=account&limit=2&taken_at='
                .urlencode($status['snapshot']['taken_at']).'&cursor='.urlencode((string) $page1['next_cursor']),
            ['Authorization' => 'Bearer '.$token],
        ))['data'];

        $this->assertCount(2, $page2['items']);

        // No overlap between pages.
        $ids1 = array_column($page1['items'], 'entity_id');
        $ids2 = array_column($page2['items'], 'entity_id');
        $this->assertEmpty(array_intersect($ids1, $ids2));

        // Item shape matches the sync change contract.
        $item = $page1['items'][0];
        $this->assertArrayHasKey('version', $item);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('payload', $item);
        $this->assertFalse($item['deleted']);
    }

    public function test_invalid_entity_type_is_rejected(): void
    {
        $token = $this->login();

        $response = $this->getJson(
            '/api/v1/bootstrap/data?entity_type=sale&taken_at=2026-01-01T00:00:00Z',
            ['Authorization' => 'Bearer '.$token],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_error');
    }

    public function test_missing_or_invalid_taken_at_is_rejected(): void
    {
        $token = $this->login();

        $this->getJson('/api/v1/bootstrap/data?entity_type=account', [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422);

        $this->getJson('/api/v1/bootstrap/data?entity_type=account&taken_at=not-a-date', [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422);
    }

    public function test_user_without_bootstrap_permissions_is_forbidden(): void
    {
        // Fresh company + user whose role grants no permissions.
        $company = Company::query()->create([
            'name' => 'Locked Out Co',
            'code' => 'LOCKED-'.Str::random(4),
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'No Permissions',
        ]);
        $user = User::query()->create([
            'name' => 'No Perms',
            'email' => 'noperms@example.com',
            'password_hash' => password_hash('SecretPass!123', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $token = $this->login('noperms@example.com', 'SecretPass!123');

        $response = $this->getJson('/api/v1/bootstrap', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'permission_denied');
    }

    public function test_initialization_data_is_scoped_to_the_authorized_company(): void
    {
        $companyA = config('nexabiz.seed_company_id');
        $this->seedEntity($companyA, 'account', 1);

        // Second company with its own entity.
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'code' => 'COMPANY-B',
        ]);
        $this->seedEntity((string) $companyB->id, 'account', 9);

        // ahmed belongs to COMPANY-A only; his session must never see B's data.
        $token = $this->login();
        $status = $this->decode(
            $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer '.$token])
        )['data'];

        $this->assertSame(1, $status['counts']['account']);
        $this->assertSame((string) $companyA, $status['company']['id']);
    }
}
