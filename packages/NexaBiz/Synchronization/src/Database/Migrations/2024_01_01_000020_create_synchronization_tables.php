<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_sequences', function (Blueprint $table) {
            $table->uuid('company_id')->primary();
            $table->bigInteger('next_value')->default(1);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('sync_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->integer('version')->default(1);
            $table->json('payload');
            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'entity_type', 'entity_uuid'], 'uq_sync_entity_tenant_type_uuid');
            $table->index('company_id', 'ix_sync_entities_company_id');
            $table->index(['company_id', 'entity_type'], 'ix_sync_entities_company_type');
            $table->index(['company_id', 'updated_at'], 'ix_sync_entities_company_updated');
        });

        Schema::create('sync_changes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sequence');
            $table->uuid('company_id');
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->string('operation', 16);
            $table->integer('version');
            $table->json('payload');
            $table->boolean('deleted')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'sequence'], 'uq_sync_changes_company_sequence');
            $table->index(['company_id', 'entity_type', 'sequence'], 'ix_sync_changes_company_type_sequence');
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->uuid('operation_id');
            $table->string('entity_type', 64);
            $table->uuid('entity_uuid');
            $table->string('operation_type', 16);
            $table->string('status', 32);
            $table->json('result');
            $table->uuid('user_id')->nullable();
            $table->uuid('device_id')->nullable();
            $table->timestampTz('processed_at')->useCurrent();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'operation_id'], 'uq_sync_operations_company_op');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('sync_changes');
        Schema::dropIfExists('sync_entities');
        Schema::dropIfExists('sync_sequences');
    }
};
