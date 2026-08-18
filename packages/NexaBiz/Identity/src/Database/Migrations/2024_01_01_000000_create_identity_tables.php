<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('code', 64)->unique('uq_companies_code');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('email', 320)->unique();
            $table->string('phone', 64)->nullable();
            $table->string('password_hash', 255);
            $table->string('status', 32)->default('active');
            $table->boolean('is_super_admin')->default(false);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 120)->unique();
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('system_role')->default(false);
            $table->timestampsTz();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'name'], 'uq_roles_company_name');
            $table->index('company_id', 'ix_roles_company_id');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('role_id');
            $table->uuid('permission_id');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id'], 'uq_role_permission');
        });

        Schema::create('company_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->uuid('role_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->unique(['company_id', 'user_id'], 'uq_company_user');
            $table->index('user_id', 'ix_company_users_user_id');
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->string('device_name', 200);
            $table->string('platform', 64)->default('unknown');
            $table->string('app_version', 64)->nullable();
            $table->uuid('device_identifier');
            $table->string('status', 32)->default('active');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['company_id', 'device_identifier'], 'uq_devices_company_identifier');
            $table->index('user_id', 'ix_devices_user_id');
        });

        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('device_id')->nullable();
            $table->string('refresh_token_hash', 128);
            $table->uuid('family_id');
            $table->string('status', 32)->default('active');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('replaced_by_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('last_used_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
            $table->index('user_id', 'ix_auth_sessions_user_id');
            $table->index('refresh_token_hash');
        });

        Schema::create('sync_disable_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->uuid('device_id');
            $table->string('status', 32)->default('pending');
            $table->string('message', 500)->nullable();
            $table->uuid('resolved_by_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->foreign('resolved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'status'], 'ix_sync_disable_requests_company_status');
            $table->index('device_id', 'ix_sync_disable_requests_device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_disable_requests');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
    }
};
