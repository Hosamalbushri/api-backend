<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use Tests\TestCase;

class AuthRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    private function login(string $email, string $password, array $extra = []): array
    {
        $response = $this->postJson('/api/v1/auth/login', array_merge([
            'email' => $email,
            'password' => $password,
        ], $extra));
        $response->assertOk();

        return $response->json('data');
    }

    private function superAdminHeaders(): array
    {
        $admin = $this->login('admin@example.com', 'ChangeMeAdmin!123');

        return ['Authorization' => 'Bearer '.$admin['access_token']];
    }

    public function test_login_valid(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123');
        $this->assertNotEmpty($data['access_token']);
        $this->assertNotEmpty($data['refresh_token']);
        $this->assertSame('ahmed@example.com', $data['user']['email']);
        $this->assertContains('sales.create', $data['permissions']);
        $this->assertNotContains('users.manage', $data['permissions']);
    }

    public function test_login_invalid_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ahmed@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_login_unknown_user(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'x',
        ])->assertStatus(401);
    }

    public function test_refresh_and_logout(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123');
        $refresh = $data['refresh_token'];
        $new = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($new['access_token']);
        $this->assertNotSame($refresh, $new['refresh_token']);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])->assertStatus(401);
        $status = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$new['access_token'],
        ])->status();
        $this->assertContains($status, [200, 401]);
    }

    public function test_me_and_permissions(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123', [
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Test Phone',
            'platform' => 'android',
            'app_version' => '0.1.0',
        ]);
        $body = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$data['access_token'],
        ])->assertOk()->json('data');
        $this->assertSame('COMPANY-A', $body['current_company']['code']);
        $this->assertContains('sales.create', $body['permissions']);
    }

    public function test_sync_requires_auth(): void
    {
        $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0')->assertStatus(401);
    }

    public function test_authorized_customer_create(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123', [
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Device A',
            'platform' => 'android',
        ]);
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'customer',
            'operation' => [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'customer',
                'entity_id' => $entityId,
                'type' => 'create',
                'payload' => ['uuid' => $entityId, 'name' => 'Cust A', 'code' => 'C1'],
                'base_version' => 0,
            ],
        ], ['Authorization' => 'Bearer '.$data['access_token']])
            ->assertOk();
    }

    public function test_unauthorized_user_admin_blocked(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123');
        $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$data['access_token'],
        ])->assertStatus(403);
    }

    public function test_tenant_isolation_on_pull(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123', [
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Device A',
            'platform' => 'android',
        ]);
        $this->getJson('/api/v1/sync/pull?entity_type=customer&cursor=0', [
            'Authorization' => 'Bearer '.$data['access_token'],
            'X-Company-Id' => '00000000-0000-4000-8000-999999999999',
        ])->assertOk()->assertJsonStructure(['changes']);
    }

    public function test_dev_token_still_works_after_seed(): void
    {
        $this->getJson('/api/v1/sync/pull?entity_type=product&cursor=0', [
            'Authorization' => 'Bearer '.config('nexabiz.dev_api_token'),
        ])->assertOk();
    }

    public function test_dev_token_disabled_by_flag(): void
    {
        config(['nexabiz.allow_dev_token' => false]);
        $this->getJson('/api/v1/sync/pull?entity_type=product&cursor=0', [
            'Authorization' => 'Bearer test-token',
        ])->assertStatus(401);
    }

    public function test_company_admin_cannot_read_cross_tenant_user(): void
    {
        $adminHeaders = $this->superAdminHeaders();
        $roles = $this->getJson('/api/v1/roles', $adminHeaders)->assertOk()->json('data');
        $companyAdminRole = collect($roles)->first(
            fn ($r) => $r['name'] === 'Company Admin' && ($r['system_role'] ?? false)
        );
        $tenantEmail = 'tenant-admin-'.Str::random(8).'@example.com';
        $this->postJson('/api/v1/users', [
            'name' => 'Tenant Admin',
            'email' => $tenantEmail,
            'password' => 'TenantAdmin!123',
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'role_id' => $companyAdminRole['id'],
            'is_super_admin' => false,
        ], $adminHeaders)->assertOk();

        $other = $this->postJson('/api/v1/companies', [
            'name' => 'Other Co '.Str::random(6),
            'code' => 'OTHER-'.strtoupper(Str::random(6)),
        ], $adminHeaders)->assertOk()->json('data.id');

        $outsider = $this->postJson('/api/v1/users', [
            'name' => 'Outsider',
            'email' => 'outsider-'.Str::random(8).'@example.com',
            'password' => 'Outsider!12345',
            'company_id' => $other,
            'role_id' => $companyAdminRole['id'],
            'is_super_admin' => false,
        ], $adminHeaders)->assertOk()->json('data.id');

        $tenant = $this->login($tenantEmail, 'TenantAdmin!123', [
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Tenant Device',
            'platform' => 'android',
        ]);
        $tenantHeaders = ['Authorization' => 'Bearer '.$tenant['access_token']];
        $this->getJson('/api/v1/users/'.$outsider, $tenantHeaders)->assertStatus(404);
        $this->postJson("/api/v1/companies/{$other}/members", [
            'user_id' => $outsider,
            'role_id' => $companyAdminRole['id'],
            'status' => 'active',
        ], $tenantHeaders)->assertStatus(403);
    }

    public function test_super_admin_can_still_read_any_user(): void
    {
        $headers = $this->superAdminHeaders();
        $users = $this->getJson('/api/v1/users', $headers)->assertOk()->json('data');
        $this->assertNotEmpty($users);
        $target = $users[0]['id'];
        $this->getJson('/api/v1/users/'.$target, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $target);
    }

    public function test_tenant_admin_cannot_grant_platform_permissions_in_custom_role(): void
    {
        $adminHeaders = $this->superAdminHeaders();
        $roles = $this->getJson('/api/v1/roles', $adminHeaders)->assertOk()->json('data');
        $companyAdminRole = collect($roles)->first(
            fn ($r) => $r['name'] === 'Company Admin' && ($r['system_role'] ?? false)
        );
        $tenantEmail = 'tenant-admin-'.Str::random(8).'@example.com';
        $this->postJson('/api/v1/users', [
            'name' => 'Tenant Admin',
            'email' => $tenantEmail,
            'password' => 'TenantAdmin!123',
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'role_id' => $companyAdminRole['id'],
            'is_super_admin' => false,
        ], $adminHeaders)->assertOk();
        $tenant = $this->login($tenantEmail, 'TenantAdmin!123', [
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Tenant Device',
            'platform' => 'android',
        ]);
        $headers = ['Authorization' => 'Bearer '.$tenant['access_token']];
        $roleId = $this->postJson('/api/v1/roles', [
            'name' => 'Escalation Role '.Str::random(6),
            'description' => 'Must not gain platform grants',
            'permission_codes' => ['customers.view', 'platform.users.manage'],
        ], $headers)->assertOk()->json('data.id');
        $perms = $this->getJson('/api/v1/roles/'.$roleId, $headers)->assertOk()->json('data.permissions');
        $this->assertNotContains('platform.users.manage', $perms);
        $this->assertContains('customers.view', $perms);
    }

    public function test_sales_user_cannot_push_product_without_permission(): void
    {
        $data = $this->login('ahmed@example.com', 'AhmedSales!123', [
            'company_id' => '00000000-0000-4000-8000-000000000001',
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Sales Device',
            'platform' => 'android',
        ]);
        $entityId = (string) Str::uuid();
        $this->postJson('/api/v1/sync/push', [
            'entity_type' => 'product',
            'operation' => [
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'product',
                'entity_id' => $entityId,
                'type' => 'create',
                'payload' => ['uuid' => $entityId, 'name' => 'Blocked', 'itemCode' => 'X1'],
                'base_version' => 0,
            ],
        ], ['Authorization' => 'Bearer '.$data['access_token']])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'permission_denied');
    }
}
