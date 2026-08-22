<?php

namespace NexaBiz\Initialization\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use NexaBiz\Synchronization\Models\SyncEntity;

/**
 * Seeds the starter master data (chart of accounts, current fiscal year,
 * base-currency rate) into `sync_entities` for the seed company so a
 * freshly provisioned server reports `initialized: true` through the
 * bootstrap API and clients can complete server initialization.
 *
 * Payload keys mirror the mobile clients' remote-apply contracts:
 * - account:       accountCode, name, accountType, normalBalance, level,
 *                  isGroup, isActive
 * - fiscal_year:   code, name, startDate, endDate (epoch ms), status,
 *                  baseCurrencyCode
 * - currency_rate: currencyCode, rateToBase, notes
 */
class InitializationSeeder extends Seeder
{
    /**
     * Fixed UUIDs (one per seeded row) keep re-runs idempotent through
     * updateOrCreate on the (company_id, entity_type, entity_uuid) key.
     */
    private const ENTITIES = [
        [
            'uuid' => '10000000-0000-4000-8000-000000001000',
            'type' => 'account',
            'payload' => [
                'accountCode' => '1000',
                'name' => 'Cash on Hand',
                'accountType' => 'asset',
                'normalBalance' => 'debit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '10000000-0000-4000-8000-000000001100',
            'type' => 'account',
            'payload' => [
                'accountCode' => '1100',
                'name' => 'Accounts Receivable',
                'accountType' => 'asset',
                'normalBalance' => 'debit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '10000000-0000-4000-8000-000000001200',
            'type' => 'account',
            'payload' => [
                'accountCode' => '1200',
                'name' => 'Inventory',
                'accountType' => 'asset',
                'normalBalance' => 'debit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '10000000-0000-4000-8000-000000002000',
            'type' => 'account',
            'payload' => [
                'accountCode' => '2000',
                'name' => 'Accounts Payable',
                'accountType' => 'liability',
                'normalBalance' => 'credit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '10000000-0000-4000-8000-000000003000',
            'type' => 'account',
            'payload' => [
                'accountCode' => '3000',
                'name' => "Owner's Equity",
                'accountType' => 'equity',
                'normalBalance' => 'credit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '10000000-0000-4000-8000-000000004000',
            'type' => 'account',
            'payload' => [
                'accountCode' => '4000',
                'name' => 'Sales Revenue',
                'accountType' => 'revenue',
                'normalBalance' => 'credit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '10000000-0000-4000-8000-000000005000',
            'type' => 'account',
            'payload' => [
                'accountCode' => '5000',
                'name' => 'General Expenses',
                'accountType' => 'expense',
                'normalBalance' => 'debit',
                'level' => 1,
                'isGroup' => false,
                'isActive' => true,
            ],
        ],
        [
            'uuid' => '20000000-0000-4000-8000-000000000001',
            'type' => 'fiscal_year',
            'payload' => [/* filled at runtime for the current year */],
        ],
        [
            'uuid' => '30000000-0000-4000-8000-000000005555',
            'type' => 'currency_rate',
            'payload' => [
                'currencyCode' => 'YER',
                'rateToBase' => 1.0,
                'notes' => 'العملة الرئيسية - الريال اليمني (YER)',
            ],
        ],
        [
            'uuid' => '40000000-0000-4000-8000-000000009999',
            'type' => 'company_profile',
            'payload' => [
                'name' => 'شركة النماء (الريال اليمني)',
                'defaultCurrencyCode' => 'YER',
                'fiscalYearStartMonth' => 1,
            ],
        ],
    ];

    public function run(): void
    {
        $companyId = (string) config('nexabiz.seed_company_id');
        if ($companyId === '') {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        $year = (int) $now->format('Y');
        $startMs = (int) $now->copy()->startOfYear()->format('Uv');
        $endMs = (int) $now->copy()->endOfYear()->format('Uv');

        foreach (self::ENTITIES as $entity) {
            $payload = $entity['payload'];
            if ($entity['type'] === 'fiscal_year') {
                $payload = [
                    'code' => 'FY'.$year,
                    'name' => 'Fiscal Year '.$year,
                    'startDate' => $startMs,
                    'endDate' => $endMs,
                    'status' => 'open',
                    'baseCurrencyCode' => 'YER',
                ];
            }

            SyncEntity::query()->updateOrCreate([
                'company_id' => $companyId,
                'entity_type' => $entity['type'],
                'entity_uuid' => $entity['uuid'],
            ], [
                'version' => 1,
                'payload' => $payload,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
